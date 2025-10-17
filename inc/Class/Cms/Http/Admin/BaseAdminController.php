<?php
declare(strict_types=1);

namespace Cms\Http\Admin;

use Cms\Auth\AuthService;
use Cms\Utils\UploadPathFactory;
use Cms\View\ViewEngine;
use Core\Files\PathResolver;

abstract class BaseAdminController
{
    protected ViewEngine $view;
    protected AuthService $auth;

    public function __construct(string $baseViewsPath)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->view = new ViewEngine($baseViewsPath);
        $this->auth = new AuthService();
    }

    final protected function isAjax(): bool
    {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }

        $accept = isset($_SERVER['HTTP_ACCEPT']) ? (string)$_SERVER['HTTP_ACCEPT'] : '';
        return str_contains($accept, 'application/json');
    }

    final protected function token(): string
    {
        if (empty($_SESSION['csrf_admin'])) {
            $_SESSION['csrf_admin'] = bin2hex(random_bytes(16));
        }

        return (string)$_SESSION['csrf_admin'];
    }

    final protected function assertCsrf(): void
    {
        $incoming = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
        if (empty($_SESSION['csrf_admin']) || !hash_equals((string)$_SESSION['csrf_admin'], (string)$incoming)) {
            http_response_code(419);
            echo 'CSRF token invalid';
            exit;
        }
    }

    final protected function flash(string $type, string $message): void
    {
        $_SESSION['_flash'] = ['type' => $type, 'msg' => $message];
    }

    final protected function redirect(string $url, ?string $flashType = null, ?string $flashMessage = null): never
    {
        if ($flashType !== null && $flashMessage !== null) {
            $this->flash($flashType, $flashMessage);
        }

        $flash = $_SESSION['_flash'] ?? null;

        if ($this->isAjax()) {
            $payload = [
                'success'  => $this->flashIndicatesSuccess($flash),
                'redirect' => $url,
            ];

            if (is_array($flash)) {
                $payload['flash'] = [
                    'type' => (string)($flash['type'] ?? ''),
                    'msg'  => (string)($flash['msg'] ?? ''),
                ];
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($payload, JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Location: ' . $url);
        exit;
    }

    final protected function renderAdmin(string $template, array $data = []): void
    {
        $payload = array_replace(
            [
                'currentUser' => $this->auth->user(),
                'flash'       => $this->pullFlash(),
                'csrf'        => $this->token(),
            ],
            $data,
        );

        $this->view->render($template, $payload);
    }

    final protected function uploadPaths(): PathResolver
    {
        return UploadPathFactory::forUploads();
    }

    private function pullFlash(): ?array
    {
        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);

        return $flash;
    }

    private function flashIndicatesSuccess($flash): bool
    {
        if (!is_array($flash)) {
            return true;
        }

        $type = strtolower((string)($flash['type'] ?? ''));

        return $type !== 'danger';
    }
}
