<?php
declare(strict_types=1);

use Core\Cron\Cron;

if (!function_exists('add_cron_interval')) {
    function add_cron_interval(string $name, int $seconds, string $display): void
    {
        Cron::instance()->registerInterval($name, $seconds, $display);
    }
}

if (!function_exists('add_cron_hook')) {
    function add_cron_hook(string $hook, callable $callback): void
    {
        Cron::instance()->registerCallback($hook, $callback);
    }
}

if (!function_exists('schedule_cron_event')) {
    function schedule_cron_event(string $hook, string $schedule, array $args = []): void
    {
        Cron::instance()->scheduleRecurring($hook, $schedule, $args);
    }
}

if (!function_exists('register_cron_event')) {
    function register_cron_event(string $hook, callable $callback, string $schedule, array $args = []): void
    {
        $cron = Cron::instance();
        $cron->registerCallback($hook, $callback);
        $cron->scheduleRecurring($hook, $schedule, $args);
    }
}

if (!function_exists('schedule_single_cron_event')) {
    function schedule_single_cron_event(string $hook, \DateTimeImmutable|int $runAt, array $args = []): void
    {
        $date = $runAt instanceof \DateTimeImmutable
            ? $runAt
            : (new \DateTimeImmutable())->setTimestamp((int)$runAt);

        Cron::instance()->scheduleSingle($hook, $date, $args);
    }
}

if (!function_exists('unschedule_cron_event')) {
    function unschedule_cron_event(string $hook, array $args = []): void
    {
        Cron::instance()->unschedule($hook, $args);
    }
}

add_cron_hook('cms_cron_cleanup_logs', static function (): void {
    Cron::instance()->purgeLogsOlderThan(30);
});

schedule_cron_event('cms_cron_cleanup_logs', 'daily');
