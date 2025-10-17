<?php
declare(strict_types=1);

namespace Cms\Http;

use Cms\Auth\AuthService;
use Cms\View\ViewEngine;

final class AdminAuthController
{
    private ViewEngine $view;
    private AuthService $auth;

    public function __construct(string $baseViewsPath)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $this->view = new ViewEngine($baseViewsPath);
        $this->auth = new AuthService();
    }

    public function handle(string $action): void
    {
        switch ($action) {
            case 'login':
            default:
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $this->doLogin();
                } else {
                    $this->showLogin();
                }
                return;

            case 'logout':
                $this->auth->logout();
                if ($this->isAjax()) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => true, 'redirect' => 'admin.php?r=auth&a=login'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                header('Location: admin.php?r=auth&a=login');
                exit;
        }
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
        return $_SESSION['csrf_admin'];
    }

    private function assertCsrf(): void
    {
        $in = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
        if (empty($_SESSION['csrf_admin']) || !hash_equals($_SESSION['csrf_admin'], (string)$in)) {
            http_response_code(419);
            echo 'CSRF token invalid';
            exit;
        }
    }

    private function showLogin(?string $error = null): void
    {
        $this->view->render('auth/login', [
            'pageTitle' => 'Přihlášení',
            'csrf'      => $this->token(),
            'error'     => $error,
        ]);
    }

    private function doLogin(): void
    {
        try {
            $this->assertCsrf();

            $email = trim((string)($_POST['email'] ?? ''));
            $pass  = (string)($_POST['password'] ?? '');

            if ($email === '' || $pass === '') {
                throw new \RuntimeException('Vyplň e-mail i heslo.');
            }
            if (!$this->auth->attempt($email, $pass)) {
                throw new \RuntimeException('Neplatné přihlašovací údaje nebo účet není aktivní.');
            }

            if ($this->isAjax()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'redirect' => 'admin.php'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            header('Location: admin.php');
            exit;

        } catch (\Throwable $e) {
            if ($this->isAjax()) {
                http_response_code(400);
                $payload = [
                    'success' => false,
                    'error'   => $e->getMessage(),
                    'flash'   => [
                        'type' => 'danger',
                        'msg'  => $e->getMessage(),
                    ],
                ];
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($payload, JSON_UNESCAPED_UNICODE);
                exit;
            }

            $this->showLogin($e->getMessage());
        }
    }
}
