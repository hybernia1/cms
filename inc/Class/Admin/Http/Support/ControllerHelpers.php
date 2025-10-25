<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Support;

final class ControllerHelpers
{
    private const CSRF_SESSION_KEY = 'csrf_admin';

    public static function isAjax(): bool
    {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }

        $accept = isset($_SERVER['HTTP_ACCEPT']) ? (string)$_SERVER['HTTP_ACCEPT'] : '';
        return str_contains($accept, 'application/json');
    }

    public static function csrfToken(string $sessionKey = self::CSRF_SESSION_KEY): string
    {
        if (empty($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = bin2hex(random_bytes(16));
        }

        return (string)$_SESSION[$sessionKey];
    }

    public static function assertCsrf(string $sessionKey = self::CSRF_SESSION_KEY): void
    {
        $incoming = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
        if (empty($_SESSION[$sessionKey]) || !hash_equals((string)$_SESSION[$sessionKey], (string)$incoming)) {
            if (self::isAjax()) {
                self::jsonError('CSRF token invalid', status: 419);
            }

            http_response_code(419);
            header('Content-Type: text/plain; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            echo 'CSRF token invalid';
            exit;
        }
    }

    public static function jsonResponse(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function jsonSuccess(array $data = [], int $status = 200): never
    {
        $payload = ['success' => true, 'ok' => true];

        if ($data !== []) {
            $payload = array_merge($payload, $data);
        }

        self::jsonResponse($payload, $status);
    }

    public static function jsonError(string|array $errors, array $data = [], int $status = 400): never
    {
        $payload = array_merge(['success' => false, 'ok' => false], $data);
        $normalized = self::normalizeErrors($errors);

        if (count($normalized) === 1) {
            $payload['error'] = $normalized[0];
        } elseif ($normalized !== []) {
            $payload['errors'] = $normalized;
        }

        self::jsonResponse($payload, $status);
    }

    public static function jsonRedirect(string $url, bool $success = true, ?array $flash = null, int $status = 200): never
    {
        $payload = [
            'success'  => $success,
            'ok'       => $success,
            'redirect' => $url,
        ];

        if ($flash !== null) {
            $payload['flash'] = [
                'type' => (string)($flash['type'] ?? ''),
                'msg'  => (string)($flash['msg'] ?? ''),
            ];
        }

        self::jsonResponse($payload, $status);
    }

    /**
     * @return list<string>
     */
    private static function normalizeErrors(string|array $errors): array
    {
        if (is_string($errors)) {
            $errors = [$errors];
        }

        $normalized = [];

        foreach ($errors as $error) {
            if (is_string($error)) {
                $trimmed = trim($error);
                if ($trimmed !== '') {
                    $normalized[] = $trimmed;
                }
                continue;
            }

            if (is_array($error)) {
                foreach (self::normalizeErrors($error) as $nested) {
                    $normalized[] = $nested;
                }
            }
        }

        return $normalized;
    }
}
