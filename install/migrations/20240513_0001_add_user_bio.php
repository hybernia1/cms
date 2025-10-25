<?php
declare(strict_types=1);

use Core\Database\Migrations\Migration;

final class _20240513_0001_add_user_bio extends Migration
{
    public function up(PDO $pdo): void
    {
        if ($this->columnExists($pdo, 'users', 'bio')) {
            return;
        }

        $pdo->exec('ALTER TABLE `users` ADD COLUMN `bio` TEXT NULL AFTER `avatar_path`');
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
