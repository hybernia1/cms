<?php
declare(strict_types=1);

namespace Cms\Auth;

final class Passwords
{
    public static function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_DEFAULT);
    }

    public static function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }

    public static function token(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }
}
