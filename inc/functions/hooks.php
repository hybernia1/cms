<?php
declare(strict_types=1);

/**
 * Lightweight hooks implementation inspired by WordPress actions/filters.
 *
 * @var array<string,array<int,list<callable>>> $GLOBALS['cms_hooks']
 */
$GLOBALS['cms_hooks'] = $GLOBALS['cms_hooks'] ?? [];

/**
 * Register a callback that should run when the given hook is triggered.
 */
function cms_add_action(string $hook, callable $callback, int $priority = 10): void
{
    $hook = trim($hook);
    if ($hook === '') {
        return;
    }

    if (!isset($GLOBALS['cms_hooks'][$hook])) {
        $GLOBALS['cms_hooks'][$hook] = [];
    }

    if (!isset($GLOBALS['cms_hooks'][$hook][$priority])) {
        $GLOBALS['cms_hooks'][$hook][$priority] = [];
    }

    $GLOBALS['cms_hooks'][$hook][$priority][] = $callback;
}

/**
 * Execute all callbacks registered for the given hook.
 */
function cms_do_action(string $hook, mixed ...$args): void
{
    foreach (cms_resolve_hook($hook) as $callback) {
        $callback(...$args);
    }
}

/**
 * Register a callback that modifies a value when the hook is applied.
 */
function cms_add_filter(string $hook, callable $callback, int $priority = 10): void
{
    cms_add_action($hook, $callback, $priority);
}

/**
 * Pass a value through all filter callbacks registered for the hook.
 */
function cms_apply_filters(string $hook, mixed $value, mixed ...$args): mixed
{
    $result = $value;

    foreach (cms_resolve_hook($hook) as $callback) {
        $result = $callback($result, ...$args);
    }

    return $result;
}

/**
 * @return list<callable>
 */
function cms_resolve_hook(string $hook): array
{
    $hook = trim($hook);
    if ($hook === '') {
        return [];
    }

    $callbacks = $GLOBALS['cms_hooks'][$hook] ?? [];
    if ($callbacks === []) {
        return [];
    }

    ksort($callbacks);

    $ordered = [];
    foreach ($callbacks as $priority => $items) {
        foreach ($items as $callback) {
            if (is_callable($callback)) {
                $ordered[] = $callback;
            }
        }
    }

    return $ordered;
}
