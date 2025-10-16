<?php
declare(strict_types=1);

namespace Cms\Http\Middleware;

final class CsrfGuard
{
    public const KEY = 'csrf';

    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(16));
        }
        return $_SESSION[self::KEY];
    }

    public static function assert(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $hdr = $_SERVER['HTTP_X_CSRF'] ?? '';
        $frm = $_POST['csrf'] ?? $_GET['csrf'] ?? '';
        $input = $hdr ?: $frm;
        if (empty($_SESSION[self::KEY]) || !hash_equals($_SESSION[self::KEY], (string)$input)) {
            http_response_code(419);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok'=>false,'error'=>'CSRF token invalid'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
