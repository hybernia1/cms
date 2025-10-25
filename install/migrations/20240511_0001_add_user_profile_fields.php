<?php
declare(strict_types=1);

use Core\Database\Migrations\Migration;

final class _20240511_0001_add_user_profile_fields extends Migration
{
    public function up(PDO $pdo): void
    {
        if (!$this->columnExists($pdo, 'users', 'website_url')) {
            $pdo->exec('ALTER TABLE `users` ADD COLUMN `website_url` VARCHAR(255) NULL AFTER `slug`');
        }

        if (!$this->columnExists($pdo, 'users', 'avatar_path')) {
            $pdo->exec('ALTER TABLE `users` ADD COLUMN `avatar_path` VARCHAR(255) NULL AFTER `website_url`');
        }
    }

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $table = trim($table);
        $column = trim($column);
        if ($table === '' || $column === '') {
            return false;
        }

        $sql = 'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE()'
            . ' AND TABLE_NAME = :table'
            . ' AND COLUMN_NAME = :column'
            . ' LIMIT 1';

        $statement = $pdo->prepare($sql);
        if ($statement === false) {
            return false;
        }

        $statement->execute([
            'table' => $table,
            'column' => $column,
        ]);

        return (bool)$statement->fetch(PDO::FETCH_NUM);
    }
}
