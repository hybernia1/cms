<?php
declare(strict_types=1);

namespace Core\Cron;

use Core\Database\Init;
use Core\Database\SchemaChecker;
use DateInterval;
use DateTimeImmutable;
use PDO;
use Throwable;

final class Cron
{
    private const TABLE_EVENTS = 'cron_events';
    private const TABLE_LOGS   = 'cron_logs';

    private const MAX_BATCH_SIZE   = 10;
    private const MAX_BATCH_ROUNDS = 5;
    private const DEFAULT_LOCK_SECONDS = 120;

    private static ?self $instance = null;

    /** @var array<string,list<callable>> */
    private array $callbacks = [];

    /** @var array<string,array{interval:int,display:string}> */
    private array $intervals;

    /** @var array<string,array{hook:string,schedule:?string,interval:int,args:array}> */
    private array $queuedRecurring = [];

    /** @var array<string,array{hook:string,runAt:DateTimeImmutable,args:array}> */
    private array $queuedSingle = [];

    private bool $enabled = false;
    private bool $tickInProgress = false;

    private function __construct()
    {
        $this->intervals = [
            'minute'      => ['interval' => 60,    'display' => 'Každou minutu'],
            'five_minutes'=> ['interval' => 300,   'display' => 'Každých 5 minut'],
            'hourly'      => ['interval' => 3600,  'display' => 'Každou hodinu'],
            'twicedaily'  => ['interval' => 43200, 'display' => 'Dvakrát denně'],
            'daily'       => ['interval' => 86400, 'display' => 'Denně'],
        ];
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function ensureReady(): bool
    {
        if ($this->enabled) {
            return true;
        }

        return $this->ensureAvailability();
    }

    public function registerInterval(string $name, int $seconds, string $display): void
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Název intervalu nesmí být prázdný.');
        }
        if ($seconds <= 0) {
            throw new \InvalidArgumentException('Interval musí být kladné číslo v sekundách.');
        }

        $this->intervals[$name] = [
            'interval' => $seconds,
            'display'  => $display,
        ];
    }

    /**
     * @return array<string,array{interval:int,display:string}>
     */
    public function intervals(): array
    {
        return $this->intervals;
    }

    public function registerCallback(string $hook, callable $callback): void
    {
        $hook = trim($hook);
        if ($hook === '') {
            throw new \InvalidArgumentException('Cron hook nesmí být prázdný.');
        }

        $this->callbacks[$hook][] = $callback;
    }

    public function scheduleRecurring(string $hook, string $schedule, array $args = []): void
    {
        $hook     = trim($hook);
        $schedule = trim($schedule);
        if ($hook === '') {
            throw new \InvalidArgumentException('Cron hook nesmí být prázdný.');
        }
        if ($schedule === '') {
            throw new \InvalidArgumentException('Cron schedule nesmí být prázdný.');
        }

        $interval = $this->intervals[$schedule]['interval'] ?? null;
        if ($interval === null) {
            throw new \InvalidArgumentException("Neznámý interval cron plánu '{$schedule}'.");
        }

        $key = $this->eventKey($hook, $args);
        $this->queuedRecurring[$key] = [
            'hook'     => $hook,
            'schedule' => $schedule,
            'interval' => $interval,
            'args'     => $this->normalizeArgs($args),
        ];

        if ($this->ensureAvailability()) {
            $this->flushQueuedEvents();
        }
    }

    public function scheduleSingle(string $hook, DateTimeImmutable $runAt, array $args = []): void
    {
        $hook = trim($hook);
        if ($hook === '') {
            throw new \InvalidArgumentException('Cron hook nesmí být prázdný.');
        }

        $key = $this->eventKey($hook, $args);
        $normalizedArgs = $this->normalizeArgs($args);
        $this->queuedSingle[$key] = [
            'hook'  => $hook,
            'runAt' => $runAt,
            'args'  => $normalizedArgs,
        ];

        if ($this->ensureAvailability()) {
            $this->flushQueuedEvents();
        }
    }

    public function unschedule(string $hook, array $args = []): void
    {
        $key = $this->eventKey($hook, $args);
        unset($this->queuedRecurring[$key], $this->queuedSingle[$key]);

        if (!$this->ensureAvailability()) {
            return;
        }

        $hash = $this->argsHash($this->normalizeArgs($args));
        $stmt = Init::pdo()->prepare(
            'DELETE FROM ' . self::TABLE_EVENTS . ' WHERE hook = :hook AND args_hash = :hash'
        );
        $stmt->execute([
            'hook' => $hook,
            'hash' => $hash,
        ]);
    }

    public function tick(): void
    {
        if ($this->tickInProgress) {
            return;
        }

        if (!$this->ensureAvailability()) {
            return;
        }

        $this->tickInProgress = true;
        try {
            $this->runDueEvents();
        } finally {
            $this->tickInProgress = false;
        }
    }

    public function purgeLogsOlderThan(int $days): int
    {
        if ($days <= 0) {
            return 0;
        }

        if (!$this->ensureAvailability()) {
            return 0;
        }

        $threshold = (new DateTimeImmutable('now'))->sub(new DateInterval('P' . $days . 'D'));
        $stmt = Init::pdo()->prepare(
            'DELETE FROM ' . self::TABLE_LOGS . ' WHERE started_at < :threshold'
        );
        $stmt->execute(['threshold' => $threshold->format('Y-m-d H:i:s')]);

        return $stmt->rowCount();
    }

    private function runDueEvents(): void
    {
        $round = 0;
        do {
            $round++;
            $dueEvents = $this->fetchDueEvents();
            if ($dueEvents === []) {
                break;
            }

            foreach ($dueEvents as $event) {
                $this->executeEvent($event);
            }
        } while ($round < self::MAX_BATCH_ROUNDS);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function fetchDueEvents(): array
    {
        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $sql = 'SELECT id, hook, schedule, interval_seconds, args, args_hash, next_run, last_run, active '
            . 'FROM ' . self::TABLE_EVENTS
            . ' WHERE active = 1'
            . '   AND next_run IS NOT NULL'
            . '   AND next_run <= :now'
            . '   AND (locked_until IS NULL OR locked_until <= :now)'
            . ' ORDER BY next_run ASC'
            . ' LIMIT ' . self::MAX_BATCH_SIZE;

        $stmt = Init::pdo()->prepare($sql);
        $stmt->execute(['now' => $now]);

        /** @var list<array<string,mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $rows;
    }

    /**
     * @param array<string,mixed> $event
     */
    private function executeEvent(array $event): void
    {
        $id       = (int)$event['id'];
        $hook     = (string)$event['hook'];
        $interval = (int)$event['interval_seconds'];
        $schedule = $event['schedule'] !== null ? (string)$event['schedule'] : null;
        $argsJson = is_string($event['args']) ? $event['args'] : '';
        $args     = $this->decodeArgs($argsJson);
        $callbacks = $this->callbacks[$hook] ?? [];

        $pdo = Init::pdo();
        $now = new DateTimeImmutable('now');
        $lockUntil = $now->modify('+' . self::DEFAULT_LOCK_SECONDS . ' seconds')->format('Y-m-d H:i:s');

        $lockStmt = $pdo->prepare(
            'UPDATE ' . self::TABLE_EVENTS . ' SET running = 1, locked_until = :lock_until, updated_at = :now'
            . ' WHERE id = :id AND (locked_until IS NULL OR locked_until <= :now)'
            . '   AND active = 1'
        );
        $lockResult = $lockStmt->execute([
            'lock_until' => $lockUntil,
            'now'        => $now->format('Y-m-d H:i:s'),
            'id'         => $id,
        ]);

        if ($lockResult === false || $lockStmt->rowCount() === 0) {
            return;
        }

        $status    = 'success';
        $message   = null;
        $start     = new DateTimeImmutable('now');
        $startedAt = $start->format('Y-m-d H:i:s');
        $duration  = 0;

        $callbacksRan = 0;
        try {
            if ($callbacks === []) {
                $status  = 'skipped';
                $message = 'Cron hook nemá registrovaný callback.';
            } else {
                foreach ($callbacks as $callback) {
                    $callbacksRan++;
                    $callStart = microtime(true);
                    $callback(...$args);
                    $duration += (int)round((microtime(true) - $callStart) * 1000);
                }
            }
        } catch (Throwable $e) {
            $status  = 'error';
            $message = $e->getMessage();
        }

        $finishedAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $nextRun = null;
        $active  = 0;
        if ($interval > 0) {
            $nextRun = (new DateTimeImmutable('now'))->modify('+' . $interval . ' seconds')->format('Y-m-d H:i:s');
            $active  = 1;
        }

        $updateStmt = $pdo->prepare(
            'UPDATE ' . self::TABLE_EVENTS
            . ' SET last_run = :last_run,'
            . '     next_run = :next_run,'
            . '     running = 0,'
            . '     active = :active,'
            . '     locked_until = NULL,'
            . '     updated_at = :updated_at'
            . ' WHERE id = :id'
        );
        $updateStmt->execute([
            'last_run'   => $startedAt,
            'next_run'   => $nextRun,
            'active'     => $active,
            'updated_at' => $finishedAt,
            'id'         => $id,
        ]);

        $this->logEvent([
            'event_id'    => $id,
            'hook'        => $hook,
            'status'      => $status,
            'message'     => $message,
            'started_at'  => $startedAt,
            'finished_at' => $finishedAt,
            'duration_ms' => $duration,
            'callbacks'   => $callbacksRan,
            'args'        => $args,
            'schedule'    => $schedule,
        ]);
    }

    /**
     * @param array<string,mixed> $log
     */
    private function logEvent(array $log): void
    {
        $contextJson = $this->encodeJson([
            'args'      => $log['args'],
            'schedule'  => $log['schedule'],
            'callbacks' => $log['callbacks'],
        ]);

        $stmt = Init::pdo()->prepare(
            'INSERT INTO ' . self::TABLE_LOGS
            . ' (event_id, hook, status, message, started_at, finished_at, duration_ms, context)'
            . ' VALUES (:event_id, :hook, :status, :message, :started_at, :finished_at, :duration_ms, :context)'
        );

        $stmt->execute([
            'event_id'    => $log['event_id'],
            'hook'        => $log['hook'],
            'status'      => $log['status'],
            'message'     => $log['message'],
            'started_at'  => $log['started_at'],
            'finished_at' => $log['finished_at'],
            'duration_ms' => $log['duration_ms'],
            'context'     => $contextJson,
        ]);
    }

    private function ensureAvailability(): bool
    {
        try {
            $checker = new SchemaChecker();
            $hasEvents = $checker->hasTable(self::TABLE_EVENTS);
            $hasLogs   = $checker->hasTable(self::TABLE_LOGS);
            $this->enabled = $hasEvents && $hasLogs;
        } catch (Throwable) {
            $this->enabled = false;
        }

        if ($this->enabled) {
            $this->flushQueuedEvents();
        }

        return $this->enabled;
    }

    private function flushQueuedEvents(): void
    {
        if (!$this->enabled) {
            return;
        }

        if ($this->queuedRecurring !== []) {
            foreach ($this->queuedRecurring as $data) {
                $this->persistRecurring($data);
            }
            $this->queuedRecurring = [];
        }

        if ($this->queuedSingle !== []) {
            foreach ($this->queuedSingle as $data) {
                $this->persistSingle($data);
            }
            $this->queuedSingle = [];
        }
    }

    /**
     * @param array{hook:string,schedule:?string,interval:int,args:array} $event
     */
    private function persistRecurring(array $event): void
    {
        $hook     = $event['hook'];
        $schedule = $event['schedule'];
        $interval = $event['interval'];
        $args     = $event['args'];
        $argsJson = $this->encodeJson($args);
        $hash     = $this->argsHash($args);
        $now      = new DateTimeImmutable('now');
        $nextRun  = $now->modify('+' . $interval . ' seconds')->format('Y-m-d H:i:s');
        $nowStr   = $now->format('Y-m-d H:i:s');

        $pdo = Init::pdo();
        $stmt = $pdo->prepare(
            'SELECT id, active, interval_seconds, schedule, next_run FROM ' . self::TABLE_EVENTS
            . ' WHERE hook = :hook AND args_hash = :hash LIMIT 1'
        );
        $stmt->execute(['hook' => $hook, 'hash' => $hash]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (is_array($existing)) {
            $updates = [];
            if ((int)$existing['interval_seconds'] !== $interval) {
                $updates['interval_seconds'] = $interval;
            }
            $existingSchedule = $existing['schedule'] !== null ? (string)$existing['schedule'] : null;
            if ($existingSchedule !== $schedule) {
                $updates['schedule'] = $schedule;
            }
            if ((int)$existing['active'] !== 1) {
                $updates['active'] = 1;
                $updates['next_run'] = $nextRun;
            } elseif ($existing['next_run'] === null) {
                $updates['next_run'] = $nextRun;
            }
            if ($updates !== []) {
                $updates['args']       = $argsJson;
                $updates['updated_at'] = $nowStr;
                $updates['running']    = 0;
                $updates['locked_until'] = null;

                $setClauses = [];
                foreach (array_keys($updates) as $column) {
                    $setClauses[] = $column . ' = :' . $column;
                }

                $sql = 'UPDATE ' . self::TABLE_EVENTS
                    . ' SET ' . implode(', ', $setClauses)
                    . ' WHERE id = :id';

                $updates['id'] = (int)$existing['id'];
                $pdo->prepare($sql)->execute($updates);
            }

            return;
        }

        $insert = $pdo->prepare(
            'INSERT INTO ' . self::TABLE_EVENTS
            . ' (hook, schedule, interval_seconds, args, args_hash, next_run, last_run, running, active, locked_until, created_at)'
            . ' VALUES (:hook, :schedule, :interval, :args, :hash, :next_run, NULL, 0, 1, NULL, :created_at)'
        );
        $insert->execute([
            'hook'       => $hook,
            'schedule'   => $schedule,
            'interval'   => $interval,
            'args'       => $argsJson,
            'hash'       => $hash,
            'next_run'   => $nextRun,
            'created_at' => $nowStr,
        ]);
    }

    /**
     * @param array{hook:string,runAt:DateTimeImmutable,args:array} $event
     */
    private function persistSingle(array $event): void
    {
        $hook  = $event['hook'];
        $runAt = $event['runAt'];
        $args  = $event['args'];
        $argsJson = $this->encodeJson($args);
        $hash     = $this->argsHash($args);
        $runAtStr = $runAt->format('Y-m-d H:i:s');
        $nowStr   = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $pdo = Init::pdo();
        $stmt = $pdo->prepare(
            'SELECT id, active, next_run FROM ' . self::TABLE_EVENTS
            . ' WHERE hook = :hook AND args_hash = :hash LIMIT 1'
        );
        $stmt->execute(['hook' => $hook, 'hash' => $hash]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (is_array($existing)) {
            if ((int)$existing['active'] === 1) {
                return;
            }

            $update = $pdo->prepare(
                'UPDATE ' . self::TABLE_EVENTS
                . ' SET interval_seconds = 0, schedule = NULL, args = :args, next_run = :next_run, '
                . ' last_run = NULL, active = 1, running = 0, locked_until = NULL, updated_at = :updated_at'
                . ' WHERE id = :id'
            );
            $update->execute([
                'args'       => $argsJson,
                'next_run'   => $runAtStr,
                'updated_at' => $nowStr,
                'id'         => (int)$existing['id'],
            ]);

            return;
        }

        $insert = $pdo->prepare(
            'INSERT INTO ' . self::TABLE_EVENTS
            . ' (hook, schedule, interval_seconds, args, args_hash, next_run, last_run, running, active, locked_until, created_at)'
            . ' VALUES (:hook, NULL, 0, :args, :hash, :next_run, NULL, 0, 1, NULL, :created_at)'
        );
        $insert->execute([
            'hook'       => $hook,
            'args'       => $argsJson,
            'hash'       => $hash,
            'next_run'   => $runAtStr,
            'created_at' => $nowStr,
        ]);
    }

    /**
     * @param array<int|string,mixed> $args
     */
    private function normalizeArgs(array $args): array
    {
        $normalized = [];
        if ($this->isAssoc($args)) {
            ksort($args);
        }

        foreach ($args as $key => $value) {
            $normalized[$key] = $this->normalizeValue($value);
        }

        return $normalized;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->normalizeArgs($value);
        }

        if ($value instanceof DateTimeImmutable) {
            return $value->format(DateTimeImmutable::ATOM);
        }

        if ($value instanceof \DateTimeInterface) {
            return (new DateTimeImmutable($value->format(DateTimeImmutable::ATOM)))->format(DateTimeImmutable::ATOM);
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string)$value;
            }

            return (array)$value;
        }

        if (is_resource($value)) {
            return 'resource';
        }

        return $value;
    }

    private function eventKey(string $hook, array $args): string
    {
        return $hook . '|' . $this->argsHash($this->normalizeArgs($args));
    }

    /**
     * @param array<int|string,mixed> $args
     */
    private function argsHash(array $args): string
    {
        $json = $this->encodeJson($args);
        return md5($json === '' ? '[]' : $json);
    }

    private function decodeArgs(string $json): array
    {
        if ($json === '') {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function encodeJson(array $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable) {
            return '[]';
        }
    }

    private function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
