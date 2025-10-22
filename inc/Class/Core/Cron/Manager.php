<?php
declare(strict_types=1);

namespace Core\Cron;

use RuntimeException;
use Throwable;

final class Manager
{
    private TaskRepositoryInterface $repository;
    private Scheduler $scheduler;
    private HookRegistry $hooks;
    private bool $lazyRunnerRegistered = false;
    private bool $ranInRequest = false;

    public function __construct(
        TaskRepositoryInterface $repository,
        Scheduler $scheduler,
        HookRegistry $hooks
    ) {
        $this->repository = $repository;
        $this->scheduler  = $scheduler;
        $this->hooks      = $hooks;
    }

    public function registerHook(string $hook, callable $callback): void
    {
        $this->hooks->register($hook, $callback);
    }

    public function unregisterHook(string $hook): void
    {
        $this->hooks->unregister($hook);
    }

    public function hooks(): HookRegistry
    {
        return $this->hooks;
    }

    /**
     * @param array<int|string,mixed> $args
     */
    public function scheduleEvent(string $hook, int|string|\DateInterval $interval, array $args = [], ?int $firstRunAt = null): Task
    {
        $intervalSeconds = $this->scheduler->intervalToSeconds($interval);
        $runAt = $firstRunAt ?? $this->scheduler->nextRun($intervalSeconds);

        $task = new Task(null, $hook, $args, $runAt, $intervalSeconds);

        return $this->repository->save($task);
    }

    /**
     * @param array<int|string,mixed> $args
     */
    public function scheduleAt(string $hook, int $timestamp, array $args = [], ?int $intervalSeconds = null): Task
    {
        $task = new Task(null, $hook, $args, $timestamp, $intervalSeconds);
        return $this->repository->save($task);
    }

    public function unscheduleEvent(string $hook): void
    {
        $this->repository->deleteByHook($hook);
    }

    public function runDueTasks(): int
    {
        $now = $this->scheduler->now();
        $due = $this->repository->dueTasks($now);
        $executed = 0;

        foreach ($due as $task) {
            $logId = $this->repository->logStart($task, $now);
            $callback = $this->hooks->get($task->hook());

            try {
                if (!$callback) {
                    throw new RuntimeException('No cron hook registered for ' . $task->hook());
                }

                $callback(...$task->args());
                $finishedAt = $this->scheduler->now();
                $this->repository->logFinish($logId, $finishedAt);
                $this->handlePostRun($task, $finishedAt);
                $executed++;
            } catch (Throwable $e) {
                $failedAt = $this->scheduler->now();
                $this->repository->logFailure($logId, $failedAt, '[' . $e::class . '] ' . $e->getMessage());
                $this->handlePostRun($task, $failedAt);
            }
        }

        return $executed;
    }

    public function maybeRun(): int
    {
        if ($this->ranInRequest) {
            return 0;
        }

        $this->ranInRequest = true;

        return $this->runDueTasks();
    }

    public function registerLazyRunner(): void
    {
        if ($this->lazyRunnerRegistered) {
            return;
        }

        $this->lazyRunnerRegistered = true;
        register_shutdown_function(function (): void {
            $this->maybeRun();
        });
    }

    public function lastRun(string $hook): ?int
    {
        return $this->repository->lastRun($hook);
    }

    private function handlePostRun(Task $task, int $referenceTime): void
    {
        $interval = $task->intervalSeconds();
        if ($interval === null) {
            $this->repository->delete($task);
            return;
        }

        $nextBase = max($task->scheduledAt(), $referenceTime);
        $nextRun = $this->scheduler->nextRun($interval, $nextBase);
        $this->repository->save($task->withScheduledAt($nextRun));
    }
}
