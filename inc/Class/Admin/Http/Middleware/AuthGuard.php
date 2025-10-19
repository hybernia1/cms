<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Middleware;

use Cms\Admin\Auth\AuthService;

final class AuthGuard
{
    public static function requireLogin(?string $role = null): void
    {
        $auth = new AuthService();
        if (!$auth->check()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok'=>false,'error'=>'Unauthorized'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($role && !$auth->requireRole($role)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok'=>false,'error'=>'Forbidden'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
