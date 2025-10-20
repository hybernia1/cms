<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Controllers;

use Core\Database\Migrations\Migrator;
use Cms\Admin\Utils\AdminNavigation;

final class MigrationsController extends BaseAdminController
{
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

        $this->renderAdmin('migrations/index', [
            'pageTitle' => 'Migrace',
            'nav'       => AdminNavigation::build('settings:migrations'),
            'all'       => $all,
            'applied'   => $applied,
        ]);
    }

    private function run(): void
    {
        $this->assertCsrf();

        $m = $this->migrator();
        $log = [];
        $logger = static function (string $message) use (&$log): void {
            if ($message === '') {
                return;
            }
            $log[] = $message;
        };

        $success = true;
        $count = 0;
        $message = '';

        try {
            $count = $m->runPending($logger);
            $message = $count > 0
                ? "Spuštěno migrací: {$count}"
                : 'Žádné čekající migrace.';
            if ($count === 0 && !$log) {
                $logger($message);
            }
        } catch (\Throwable $e) {
            $success = false;
            $message = 'Chyba migrace: ' . $e->getMessage();
            $logger($message);
        }

        $applied = array_keys($m->appliedMap());

        $this->jsonResponse([
            'success' => $success,
            'status'  => $success ? 'success' : 'error',
            'message' => $message,
            'count'   => $count,
            'applied' => $applied,
            'log'     => $log,
            'flash'   => [
                'type' => $success ? 'success' : 'danger',
                'msg'  => $message,
            ],
        ]);
    }

    private function rollback(): void
    {
        $this->assertCsrf();

        $m = $this->migrator();
        $log = [];
        $logger = static function (string $message) use (&$log): void {
            if ($message === '') {
                return;
            }
            $log[] = $message;
        };

        $success = true;
        $count = 0;
        $message = '';

        try {
            $count = $m->rollbackLastBatch($logger);
            $message = $count > 0
                ? "Rollbacknuto migrací: {$count}"
                : 'Žádné migrace k rollbacku.';
            if ($count === 0 && !$log) {
                $logger($message);
            }
        } catch (\Throwable $e) {
            $success = false;
            $message = 'Chyba rollbacku: ' . $e->getMessage();
            $logger($message);
        }

        $applied = array_keys($m->appliedMap());

        $this->jsonResponse([
            'success' => $success,
            'status'  => $success ? 'success' : 'error',
            'message' => $message,
            'count'   => $count,
            'applied' => $applied,
            'log'     => $log,
            'flash'   => [
                'type' => $success ? 'success' : 'danger',
                'msg'  => $message,
            ],
        ]);
    }
}
