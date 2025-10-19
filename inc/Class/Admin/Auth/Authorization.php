<?php
declare(strict_types=1);

namespace Cms\Admin\Auth;

final class Authorization
{
    /**
     * Je uživatel přihlášen a má jednu z požadovaných rolí?
     * @param array<string,mixed>|null $user
     * @param string[] $roles
     */
    public static function hasRole(?array $user, array $roles): bool
    {
        if (!$user) return false;
        $role = (string)($user['role'] ?? '');
        return in_array($role, $roles, true);
    }

    /** true, pokud je admin */
    public static function isAdmin(?array $user): bool
    {
        return self::hasRole($user, ['admin']);
    }
}
