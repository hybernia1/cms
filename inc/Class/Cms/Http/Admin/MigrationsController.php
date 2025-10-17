<?php
declare(strict_types=1);

namespace Cms\Http\Admin;

use Cms\View\ViewEngine;
use Cms\Auth\AuthService;
use Core\Database\Migrations\Migrator;
use Cms\Utils\AdminNavigation;

final class MigrationsController
{
    private ViewEngine $view;
    private AuthService $auth;

    public function __construct(string $baseViewsPath)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $this->view = new ViewEngine($baseViewsPath);
        $this->auth = new AuthService();
    }

    private function token(): string
    {
        if (empty($_SESSION['csrf_admin'])) $_SESSION['csrf_admin'] = bin2hex(random_bytes(16));
        return $_SESSION['csrf_admin'];
    }
    private function assertCsrf(): void
    {
        $in = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
        if (empty($_SESSION['csrf_admin']) || !hash_equals($_SESSION['csrf_admin'], (string)$in)) {
            http_response_code(419); echo 'CSRF token invalid'; exit;
        }
    }
    private function flash(string $type, string $msg): void
    {
        $_SESSION['_flash'] = ['type'=>$type,'msg'=>$msg];
    }

    private function isAjax(): bool
    {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }

        $accept = isset($_SERVER['HTTP_ACCEPT']) ? (string)$_SERVER['HTTP_ACCEPT'] : '';
        return str_contains($accept, 'application/json');
    }

    private function redirect(string $url): void
    {
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

    private function flashIndicatesSuccess($flash): bool
    {
        if (!is_array($flash)) {
            return true;
        }
        $type = strtolower((string)($flash['type'] ?? ''));
        return $type !== 'danger';
    }
    public function handle(string $action): void
    {
        switch ($action) {
            case 'run': $this->run(); return;
            case 'rollback': $this->rollback(); return;
            case 'index':
            default: $this->index(); return;
        }
    }

    private function migrator(): Migrator
    {
        $paths = require dirname(__DIR__, 5) . '/install/migrations.php';
        return new Migrator($paths);
    }

    private function index(): void
    {
        $m = $this->migrator();
        $m->ensureMigrationsTable();
        $all = $m->discover();
        $applied = $m->appliedMap();

        $this->view->render('migrations/index', [
            'pageTitle'   => 'Migrace',
            'nav'         => AdminNavigation::build('settings:migrations'),
            'currentUser' => $this->auth->user(),
            'flash'       => $_SESSION['_flash'] ?? null,
            'all'         => $all,
            'applied'     => $applied,
            'csrf'        => $this->token(),
        ]);
        unset($_SESSION['_flash']);
    }

    private function run(): void
    {
        $this->assertCsrf();
        $m = $this->migrator();
        try {
            $count = $m->runPending();
            $this->flash('success', "Spuštěno migrací: {$count}");
        } catch (\Throwable $e) {
            $this->flash('danger', 'Chyba migrace: '.$e->getMessage());
        }
        $this->redirect('admin.php?r=migrations');
    }

    private function rollback(): void
    {
        $this->assertCsrf();
        $m = $this->migrator();
        try {
            $count = $m->rollbackLastBatch();
            $this->flash('success', "Rollbacknuto migrací: {$count}");
        } catch (\Throwable $e) {
            $this->flash('danger', 'Chyba rollbacku: '.$e->getMessage());
        }
        $this->redirect('admin.php?r=migrations');
    }
}
