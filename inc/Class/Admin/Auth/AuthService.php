<?php
declare(strict_types=1);

namespace Cms\Admin\Auth;

use Core\Database\Init as DB;
use Cms\Admin\Utils\DateTimeFactory;

final class AuthService
{
    public const SESSION_KEY = 'cms_user_id';

    public function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public function attempt(string $email, string $password): bool
    {
        $user = DB::query()->table('users')->select(['id','password_hash','active','role'])
            ->where('email', '=', $email)->first();

        if (!$user || (int)$user['active'] !== 1) return false;
        if (!Passwords::verify($password, (string)$user['password_hash'])) return false;

        if (Passwords::needsRehash((string)$user['password_hash'])) {
            DB::query()->table('users')
                ->update(['password_hash' => Passwords::hash($password), 'updated_at' => DateTimeFactory::nowString()])
                ->where('id','=', (int)$user['id'])
                ->execute();
        }

        $_SESSION[self::SESSION_KEY] = (int)$user['id'];
        return true;
    }

    public function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }

    public function user(): ?array
    {
        $id = $_SESSION[self::SESSION_KEY] ?? null;
        if (!$id) return null;

        return DB::query()->table('users')->select(['id','name','email','role','active','created_at','updated_at'])
            ->where('id', '=', (int)$id)->first();
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function requireRole(string ...$roles): bool
    {
        $u = $this->user();
        return $u ? in_array($u['role'], $roles, true) : false;
    }

    public function beginPasswordReset(string $email): ?array
    {
        $u = DB::query()->table('users')->select(['id','email'])->where('email','=',$email)->first();
        if (!$u) return null;

        $token = Passwords::token(16);
        $expire = DateTimeFactory::now()->modify('+1 hour')->format('Y-m-d H:i:s'); // 1h

        DB::query()->table('users')->update([
            'token' => $token,
            'token_expire' => $expire,
            'updated_at' => DateTimeFactory::nowString(),
        ])->where('id','=', (int)$u['id'])->execute();

        return ['user_id' => (int)$u['id'], 'token' => $token, 'expires' => $expire];
    }

    public function completePasswordReset(int $userId, string $token, string $newPassword): bool
    {
        $row = DB::query()->table('users')->select(['id','token','token_expire'])
            ->where('id','=',$userId)->first();
        if (!$row) return false;

        if ((string)($row['token'] ?? '') !== $token) return false;
        if (empty($row['token_expire']) || strtotime((string)$row['token_expire']) < time()) return false;

        DB::query()->table('users')->update([
            'password_hash' => Passwords::hash($newPassword),
            'token' => null,
            'token_expire' => null,
            'updated_at' => DateTimeFactory::nowString(),
        ])->where('id','=',$userId)->execute();

        return true;
    }
}
