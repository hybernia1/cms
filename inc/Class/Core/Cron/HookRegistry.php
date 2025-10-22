<?php
declare(strict_types=1);

namespace Core\Cron;

final class HookRegistry
{
    /** @var array<string,callable> */
    private array $hooks = [];

    public function register(string $hook, callable $callback): void
    {
        $this->hooks[$hook] = $callback;
    }

    public function unregister(string $hook): void
    {
        unset($this->hooks[$hook]);
    }

    public function get(string $hook): ?callable
    {
        return $this->hooks[$hook] ?? null;
    }
}
