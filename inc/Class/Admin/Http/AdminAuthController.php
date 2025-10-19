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

            if ($email === '' || $pass === '') {
                $this->respondLoginError('Vyplňte e-mail i heslo.');
                return;
            }

            if ($this->auth->attempt($email, $pass)) {
                $this->respondLoginSuccess();
                return;
            }

            $this->respondLoginError('Nesprávný e-mail nebo heslo.');
            return;
        }

        $this->renderLogin();
    }

    private function logout(): void
    {
        $this->auth->logout();
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
            ]);
            return;
        }

        header('Location: ' . $redirect);
        exit;
    }

    private function respondLoginError(string $message): void
    {
        if ($this->isAjax()) {
            $this->json([
                'success' => false,
                'flash'   => [
                    'type' => 'danger',
                    'msg'  => $message,
                ],
            ]);
            return;
        }

        $this->renderLogin(['error' => $message]);
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
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
            http_response_code(419);
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
