<?php
declare(strict_types=1);

namespace Core\Database;

use PDO;

final class Init
{
    private static ?Connect $connect = null;
    private static bool $booted = false;

    /**
     * Jediný vstup: předáš celý config z config.php
     */
    public static function boot(array $config): void
    {
        if (self::$booted) return;
        self::$connect = new Connect($config);
        self::$booted  = true;
    }

    public static function pdo(): PDO
    {
        self::ensureBooted();
        /** @phpstan-ignore-next-line */
        return self::$connect->pdo();
    }

    public static function query(): Query
    {
        self::ensureBooted();
        /** @phpstan-ignore-next-line */
        return new Query(self::$connect);
    }

    public static function transactional(callable $fn): mixed
    {
        self::ensureBooted();
        /** @phpstan-ignore-next-line */
        return self::$connect->transactional(fn() => $fn(self::query()));
    }

    public static function lastInsertId(): string
    {
        self::ensureBooted();
        /** @phpstan-ignore-next-line */
        return self::$connect->pdo()->lastInsertId();
    }

    private static function ensureBooted(): void
    {
        if (!self::$booted || !self::$connect) {
            throw new \RuntimeException('Database not booted. Call Init::boot($config) first.');
        }
    }
}
