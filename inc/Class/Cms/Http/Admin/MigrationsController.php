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
        header('Location: admin.php?r=migrations');
        exit;
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
        header('Location: admin.php?r=migrations');
        exit;
    }
}
