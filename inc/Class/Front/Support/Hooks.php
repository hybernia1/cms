<?php
declare(strict_types=1);

namespace Cms\Front\Support;

final class Hooks
{
    /**
     * @var array<string,array<int,array<string,callable>>>
     */
    private static array $filters = [];

    public static function addFilter(string $name, callable $callback, int $priority = 10): void
    {
        $normalizedName = trim($name);
        if ($normalizedName === '') {
            return;
        }

        $priority = max(-1000, min(1000, $priority));
        $id = self::callbackId($callback);
        self::$filters[$normalizedName][$priority][$id] = $callback;
    }

    public static function removeFilter(string $name, callable $callback, int $priority = 10): void
    {
        $normalizedName = trim($name);
        if ($normalizedName === '' || !isset(self::$filters[$normalizedName])) {
            return;
        }

        $priority = max(-1000, min(1000, $priority));
        $id = self::callbackId($callback);
        unset(self::$filters[$normalizedName][$priority][$id]);
        if (self::$filters[$normalizedName][$priority] === []) {
            unset(self::$filters[$normalizedName][$priority]);
        }
        if (self::$filters[$normalizedName] === []) {
            unset(self::$filters[$normalizedName]);
        }
    }

    public static function applyFilters(string $name, mixed $value, mixed ...$args): mixed
    {
        $normalizedName = trim($name);
        if ($normalizedName === '' || !isset(self::$filters[$normalizedName])) {
            return $value;
        }

        ksort(self::$filters[$normalizedName]);
        foreach (self::$filters[$normalizedName] as $callbacks) {
            foreach ($callbacks as $callback) {
                $value = $callback($value, ...$args);
            }
        }

        return $value;
    }

    public static function clear(?string $name = null): void
    {
        if ($name === null) {
            self::$filters = [];
            return;
        }

        $normalizedName = trim($name);
        unset(self::$filters[$normalizedName]);
    }

    private static function callbackId(callable $callback): string
    {
        if (is_string($callback)) {
            return $callback;
        }

        if ($callback instanceof \Closure) {
            return spl_object_hash($callback);
        }

        if (is_array($callback)) {
            $target = $callback[0];
            $method = (string)$callback[1];
            if (is_object($target)) {
                return spl_object_hash($target) . '::' . $method;
            }

            return (string)$target . '::' . $method;
        }

        return spl_object_hash((object) $callback);
    }
}
