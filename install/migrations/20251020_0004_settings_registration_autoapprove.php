<?php
declare(strict_types=1);

use Core\Database\Migrations\Migration;

final class _20251020_0004_settings_registration_autoapprove extends Migration
{
    public function up(\PDO $pdo): void
    {
        $cols = $this->getColumns($pdo, 'settings');
        if (!in_array('registration_auto_approve', $cols, true)) {
            $pdo->exec("ALTER TABLE settings ADD COLUMN registration_auto_approve TINYINT(1) NOT NULL DEFAULT 1 AFTER allow_registration");
            $cols[] = 'registration_auto_approve';
        }

        if (!in_array('registration_auto_approve', $cols, true)) {
            return;
        }

        // Ensure existing records have a valid default
        $pdo->exec("UPDATE settings SET registration_auto_approve = 1 WHERE registration_auto_approve IS NULL");
    }

    public function down(\PDO $pdo): void
    {
        // No rollback to keep data intact
    }

    private function getColumns(\PDO $pdo, string $table): array
    {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        if (!$stmt) {
            return [];
        }
        $cols = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $cols[] = (string)($row['Field'] ?? '');
        }
        return $cols;
    }
}
