<?php
declare(strict_types=1);

use Core\Cron\Task;

if (!function_exists('cron_register_hook')) {
    function cron_register_hook(string $hook, callable $callback): void
    {
        cms_cron_manager()->registerHook($hook, $callback);
    }
}

if (!function_exists('cron_schedule_event')) {
    /**
     * @param array<int|string,mixed> $args
     */
    function cron_schedule_event(string $hook, int|string|\DateInterval $interval, array $args = []): Task
    {
        return cms_cron_manager()->scheduleEvent($hook, $interval, $args);
    }
}

if (!function_exists('cron_schedule_at')) {
    /**
     * @param array<int|string,mixed> $args
     */
    function cron_schedule_at(string $hook, int $timestamp, array $args = [], ?int $intervalSeconds = null): Task
    {
        return cms_cron_manager()->scheduleAt($hook, $timestamp, $args, $intervalSeconds);
    }
}

if (!function_exists('cron_unschedule_event')) {
    function cron_unschedule_event(string $hook): void
    {
        cms_cron_manager()->unscheduleEvent($hook);
    }
}

if (!function_exists('cron_last_run')) {
    function cron_last_run(string $hook): ?int
    {
        return cms_cron_manager()->lastRun($hook);
    }
}
