<?php
declare(strict_types=1);

namespace Cms\Http\Admin;

use Core\Database\Migrations\Migrator;
use Cms\Utils\AdminNavigation;

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
