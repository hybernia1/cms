<?php
declare(strict_types=1);

use Core\Database\Migrations\Migration;

final class _20251016_0002_settings_datetime_timezone extends Migration
{
    public function up(\PDO $pdo): void
    {
        $columns = $this->getColumns($pdo, 'settings');

        if (!in_array('date_format', $columns, true)) {
            $pdo->exec("ALTER TABLE settings ADD COLUMN date_format VARCHAR(64) NOT NULL DEFAULT 'Y-m-d'");
        }
        if (!in_array('time_format', $columns, true)) {
            $pdo->exec("ALTER TABLE settings ADD COLUMN time_format VARCHAR(64) NOT NULL DEFAULT 'H:i'");
        }
        if (!in_array('timezone', $columns, true)) {
            $pdo->exec("ALTER TABLE settings ADD COLUMN timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Prague'");
        }
    }

    public function down(\PDO $pdo): void
    {
        $columns = $this->getColumns($pdo, 'settings');
        if (in_array('date_format', $columns, true)) { $pdo->exec("ALTER TABLE settings DROP COLUMN date_format"); }
        if (in_array('time_format', $columns, true)) { $pdo->exec("ALTER TABLE settings DROP COLUMN time_format"); }
        if (in_array('timezone', $columns, true)) { $pdo->exec("ALTER TABLE settings DROP COLUMN timezone"); }
    }

    private function getColumns(\PDO $pdo, string $table): array
    {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        $cols = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $cols[] = (string)$r['Field'];
        }
        return $cols;
    }
}
