<?php
declare(strict_types=1);

namespace Cms\Admin\Http;

use Cms\Admin\Auth\AuthService;
use Cms\Admin\View\ViewEngine;

final class AdminAuthController
{
    private AuthService $auth;
    private ViewEngine $view;

    public function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->auth = new AuthService();
        $baseViewsPath = BASE_DIR . '/admin/views';
        $this->view = new ViewEngine($baseViewsPath);
        $this->view->setBasePaths($this->resolveViewPaths($baseViewsPath));
    }

    /**
     * @return array<int,string>
     */
    private function resolveViewPaths(string $baseViewsPath): array
    {
        $paths = [];

        $realBase = realpath($baseViewsPath);
        if ($realBase !== false && is_dir($realBase)) {
            $paths[] = $realBase;
        }

        $adminRoot = realpath(BASE_DIR . '/admin');
        if ($adminRoot !== false && is_dir($adminRoot)) {
            $paths[] = $adminRoot;
        }

        return $paths === [] ? [$baseViewsPath] : $paths;
    }

    public function handle(string $action): void
    {
        switch ($action) {
            case 'login':
            default:
                $this->login();
                return;

            case 'logout':
                $this->logout();
        }
    }

    private function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->assertCsrf();

            $email = trim((string)($_POST['email'] ?? ''));
            $pass  = (string)($_POST['password'] ?? '');
            $remember = !empty($_POST['remember']);

            $errors = [];

            if ($email === '') {
                $errors['email'][] = 'Vyplňte e-mail.';
            }

            if ($pass === '') {
                $errors['password'][] = 'Vyplňte heslo.';
            }

            if ($errors !== []) {
                $message = 'Vyplňte e-mail i heslo.';
                if (count($errors) === 1) {
                    $first = reset($errors);
                    if (is_array($first) && $first !== []) {
                        $message = (string)reset($first);
                    }
                }

                $this->respondLoginError($message, $email, $remember, $errors);
                return;
            }

            if ($this->auth->attempt($email, $pass)) {
                $this->finalizeLogin($remember);
                $this->respondLoginSuccess();
                return;
            }

            $errorMessage = 'Nesprávný e-mail nebo heslo.';
            $credentialErrors = [
                'email' => [$errorMessage],
                'password' => [$errorMessage],
            ];
            $this->respondLoginError($errorMessage, $email, $remember, $credentialErrors);
            return;
        }

        $this->renderLogin();
    }

    private function logout(): void
    {
        $this->auth->logout();
        $this->clearSessionCookie();
        unset($_SESSION['_flash']);

        if ($this->isAjax()) {
            $this->json([
                'success'  => true,
                'redirect' => 'admin.php?r=auth&a=login',
            ]);
            return;
        }

        header('Location: admin.php?r=auth&a=login');
        exit;
    }

    private function renderLogin(array $data = []): void
    {
        $payload = array_replace([
            'pageTitle' => 'Přihlášení do administrace',
            'csrf'      => $this->token(),
            'error'     => null,
            'errors'    => [],
            'email'     => '',
            'remember'  => false,
        ], $data);

        if ($this->isAjax()) {
            $html = $this->capture('auth/login', $payload);
            $response = [
                'success' => true,
                'html'    => $html,
                'title'   => (string)($payload['pageTitle'] ?? ''),
            ];
            if (!empty($payload['error'])) {
                $response['flash'] = [
                    'type' => 'danger',
                    'msg'  => (string)$payload['error'],
                ];
            }
            if (!empty($payload['errors'])) {
                $response['errors'] = $payload['errors'];
            }
            $this->json($response);
            return;
        }

        $this->view->render('auth/login', $payload);
    }

    private function respondLoginSuccess(): void
    {
        $redirect = 'admin.php';

        if ($this->isAjax()) {
            $this->json([
                'success'  => true,
                'redirect' => $redirect,
                'flash'    => null,
            ]);
            return;
        }

        header('Location: ' . $redirect);
        exit;
    }

    /**
     * @param array<string,mixed> $errors
     */
    private function respondLoginError(string $message, string $email = '', bool $remember = false, array $errors = []): void
    {
        if ($this->isAjax()) {
            $this->json([
                'success'  => false,
                'redirect' => null,
                'flash'    => [
                    'type' => 'danger',
                    'msg'  => $message,
                ],
                'errors'   => $errors,
            ]);
            return;
        }

        $this->renderLogin([
            'error' => $message,
            'errors' => $errors,
            'email' => $email,
            'remember' => $remember,
        ]);
    }

    private function finalizeLogin(bool $remember): void
    {
        session_regenerate_id(true);

        $params = session_get_cookie_params();
        $cookie = [
            'expires' => $remember ? time() + 60 * 60 * 24 * 30 : 0,
            'path' => $params['path'] ?? '/',
            'secure' => $params['secure'] ?? false,
            'httponly' => true,
        ];
        $domain = $params['domain'] ?? '';
        if ($domain !== '') {
            $cookie['domain'] = $domain;
        }
        if (isset($params['samesite'])) {
            $cookie['samesite'] = $params['samesite'];
        }

        setcookie(session_name(), session_id(), $cookie);
    }

    private function clearSessionCookie(): void
    {
        $params = session_get_cookie_params();
        $cookie = [
            'expires' => time() - 3600,
            'path' => $params['path'] ?? '/',
            'secure' => $params['secure'] ?? false,
            'httponly' => true,
        ];
        $domain = $params['domain'] ?? '';
        if ($domain !== '') {
            $cookie['domain'] = $domain;
        }
        if (isset($params['samesite'])) {
            $cookie['samesite'] = $params['samesite'];
        }

        setcookie(session_name(), '', $cookie);
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function isAjax(): bool
    {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }

        $accept = isset($_SERVER['HTTP_ACCEPT']) ? (string)$_SERVER['HTTP_ACCEPT'] : '';
        return str_contains($accept, 'application/json');
    }

    private function token(): string
    {
        if (empty($_SESSION['csrf_admin'])) {
            $_SESSION['csrf_admin'] = bin2hex(random_bytes(16));
        }

        return (string)$_SESSION['csrf_admin'];
    }

    private function assertCsrf(): void
    {
        $incoming = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
        if (empty($_SESSION['csrf_admin']) || !hash_equals((string)$_SESSION['csrf_admin'], (string)$incoming)) {
            if ($this->isAjax()) {
                $this->json([
                    'success' => false,
                    'ok'      => false,
                    'error'   => 'CSRF token invalid',
                ], 419);
            }

            http_response_code(419);
            header('Content-Type: text/plain; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            echo 'CSRF token invalid';
            exit;
        }
    }

    private function capture(string $template, array $payload): string
    {
        ob_start();
        try {
            $this->view->render($template, $payload);
        } finally {
            $output = ob_get_clean();
        }

        return $output === false ? '' : $output;
    }
}
