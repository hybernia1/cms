<?php
declare(strict_types=1);

namespace Core\Cron;

interface TaskRepositoryInterface
{
    public function save(Task $task): Task;

    public function delete(Task $task): void;

    public function deleteByHook(string $hook): void;

    public function findByHook(string $hook): ?Task;

    /**
     * @return list<Task>
     */
    public function dueTasks(int $now): array;

    public function lastRun(string $hook): ?int;

    public function logStart(Task $task, int $startedAt): int;

    public function logFinish(int $logId, int $finishedAt): void;

    public function logFailure(int $logId, int $failedAt, string $message): void;
}
