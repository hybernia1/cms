<?php
declare(strict_types=1);

namespace Core\Cron;

use Core\Database\Query;
use JsonException;
use RuntimeException;

final class DatabaseTaskRepository implements TaskRepositoryInterface
{
    /** @var callable():Query */
    private $queryFactory;

    /**
     * @param callable():Query $queryFactory
     */
    public function __construct(callable $queryFactory)
    {
        $this->queryFactory = $queryFactory;
    }

    public function save(Task $task): Task
    {
        $data = [
            'hook'             => $task->hook(),
            'args'             => $this->encodeArgs($task->args()),
            'scheduled_at'     => $task->scheduledAt(),
            'interval_seconds' => $task->intervalSeconds(),
        ];

        $id = $task->id();
        if ($id === null) {
            $newId = (int)$this->query()
                ->table('cron_tasks')
                ->insert($data)
                ->insertGetId();

            return $task->withId($newId);
        }

        $this->query()
            ->table('cron_tasks')
            ->where('id', '=', $id)
            ->update($data)
            ->execute();

        return $task;
    }

    public function delete(Task $task): void
    {
        $id = $task->id();
        if ($id === null) {
            return;
        }

        $this->query()
            ->table('cron_tasks')
            ->where('id', '=', $id)
            ->delete()
            ->execute();
    }

    public function deleteByHook(string $hook): void
    {
        $this->query()
            ->table('cron_tasks')
            ->where('hook', '=', $hook)
            ->delete()
            ->execute();
    }

    public function dueTasks(int $now): array
    {
        $rows = $this->query()
            ->table('cron_tasks')
            ->select(['id', 'hook', 'args', 'scheduled_at', 'interval_seconds'])
            ->where('scheduled_at', '<=', $now)
            ->orderBy('scheduled_at', 'ASC')
            ->get();

        $tasks = [];
        foreach ($rows as $row) {
            $tasks[] = $this->hydrateTask($row);
        }

        return $tasks;
    }

    public function lastRun(string $hook): ?int
    {
        $row = $this->query()
            ->table('cron_log')
            ->select(['finished_at'])
            ->where('hook', '=', $hook)
            ->where('status', '=', 'success')
            ->orderBy('finished_at', 'DESC')
            ->first();

        if (!$row || !isset($row['finished_at'])) {
            return null;
        }

        return (int)$row['finished_at'];
    }

    public function logStart(Task $task, int $startedAt): int
    {
        $data = [
            'task_id'    => $task->id(),
            'hook'       => $task->hook(),
            'status'     => 'running',
            'started_at' => $startedAt,
            'finished_at'=> null,
            'message'    => null,
        ];

        return (int)$this->query()
            ->table('cron_log')
            ->insert($data)
            ->insertGetId();
    }

    public function logFinish(int $logId, int $finishedAt): void
    {
        $this->query()
            ->table('cron_log')
            ->where('id', '=', $logId)
            ->update([
                'status'      => 'success',
                'finished_at' => $finishedAt,
            ])
            ->execute();
    }

    public function logFailure(int $logId, int $failedAt, string $message): void
    {
        $this->query()
            ->table('cron_log')
            ->where('id', '=', $logId)
            ->update([
                'status'      => 'failure',
                'finished_at' => $failedAt,
                'message'     => $this->truncateMessage($message),
            ])
            ->execute();
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrateTask(array $row): Task
    {
        $args = isset($row['args']) ? $this->decodeArgs((string)$row['args']) : [];

        return new Task(
            isset($row['id']) ? (int)$row['id'] : null,
            (string)($row['hook'] ?? ''),
            $args,
            isset($row['scheduled_at']) ? (int)$row['scheduled_at'] : 0,
            isset($row['interval_seconds']) ? ($row['interval_seconds'] === null ? null : (int)$row['interval_seconds']) : null
        );
    }

    /**
     * @param array<int|string,mixed> $args
     */
    private function encodeArgs(array $args): string
    {
        try {
            return json_encode($args, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Failed to encode cron task arguments.', 0, $e);
        }
    }

    /**
     * @return array<int|string,mixed>
     */
    private function decodeArgs(string $json): array
    {
        if ($json === '') {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Failed to decode cron task arguments.', 0, $e);
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function truncateMessage(string $message): string
    {
        if (strlen($message) <= 1000) {
            return $message;
        }

        return substr($message, 0, 1000);
    }

    private function query(): Query
    {
        $query = ($this->queryFactory)();
        if (!$query instanceof Query) {
            throw new RuntimeException('Cron repository factory must return Query instance.');
        }

        return $query;
    }
}
