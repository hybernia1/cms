<?php
declare(strict_types=1);

namespace Cms\Http;

use Cms\Http\Admin\BaseAdminController;
use RuntimeException;
use Throwable;

final class AdminAuthController extends BaseAdminController
{
    public function __construct(string $baseViewsPath)
    {
        parent::__construct($baseViewsPath);
    }

    public function handle(string $action): void
    {
        $action = $action === '' ? 'login' : $action;
        if ($action === 'index') {
            $action = 'login';
        }

        if ($action === 'logout') {
            $this->auth->logout();
            $this->redirect('admin.php?r=auth&a=login');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
            $this->processLogin();
            return;
        }

        $this->renderLogin();
    }

    private function processLogin(): void
    {
        try {
            $this->assertCsrf();
            $email = trim((string)($_POST['email'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            if ($email === '' || $password === '') {
                throw new RuntimeException('Vyplň e-mail i heslo.');
            }

            if (!$this->auth->attempt($email, $password)) {
                throw new RuntimeException('Neplatné přihlašovací údaje nebo účet není aktivní.');
            }

            $this->redirect('admin.php');
        } catch (Throwable $e) {
            $this->renderLogin($e->getMessage());
        }
    }

    private function renderLogin(?string $error = null): void
    {
        if ($this->auth->check()) {
            $this->redirect('admin.php');
            return;
        }

        $this->renderAdmin('auth/login', [
            'pageTitle' => 'Přihlášení',
            'error'     => $error,
        ]);
    }
}
