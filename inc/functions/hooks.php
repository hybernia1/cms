<?php
declare(strict_types=1);

use Cms\Front\Support\Hooks;

if (!function_exists('add_filter')) {
    function add_filter(string $hook, callable $callback, int $priority = 10): void
    {
        Hooks::addFilter($hook, $callback, $priority);
    }
}

if (!function_exists('remove_filter')) {
    function remove_filter(string $hook, callable $callback, int $priority = 10): void
    {
        Hooks::removeFilter($hook, $callback, $priority);
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
    {
        return Hooks::applyFilters($hook, $value, ...$args);
    }
}

if (!function_exists('cms_clear_filters')) {
    function cms_clear_filters(?string $hook = null): void
    {
        Hooks::clear($hook);
    }
}
