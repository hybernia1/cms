<?php
declare(strict_types=1);

use Core\Database\Migrations\Migration;

final class _20240509_0001_add_user_slug extends Migration
{
    public function up(PDO $pdo): void
    {
        if ($this->columnExists($pdo, 'users', 'slug')) {
            return;
        }

        $pdo->exec('ALTER TABLE `users` ADD COLUMN `slug` VARCHAR(190) NULL AFTER `name`');

        $rows = [];
        $statement = $pdo->query('SELECT `id`, `name` FROM `users`');
        if ($statement !== false) {
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $slugify = static function (string $value): string {
            $normalized = trim($value);
            if ($normalized === '') {
                return '';
            }

            if (function_exists('mb_strtolower')) {
                $normalized = mb_strtolower($normalized, 'UTF-8');
            } else {
                $normalized = strtolower($normalized);
            }

            $normalized = preg_replace('~[^\pL\d]+~u', '-', $normalized) ?? '';
            $normalized = trim($normalized, '-');

            if ($normalized === '') {
                return '';
            }

            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
            if (is_string($ascii) && $ascii !== '') {
                $normalized = strtolower($ascii);
            }

            $normalized = preg_replace('~[^a-z0-9-]+~', '', $normalized) ?? '';
            return trim($normalized, '-');
        };

        $exists = static function (PDO $pdo, string $slug, int $excludeId = 0): bool {
            $sql = 'SELECT `id` FROM `users` WHERE `slug` = :slug';
            $params = ['slug' => $slug];
            if ($excludeId > 0) {
                $sql .= ' AND `id` <> :id';
                $params['id'] = $excludeId;
            }

            $check = $pdo->prepare($sql);
            if ($check === false) {
                return false;
            }

            $check->execute($params);
            return (bool)$check->fetch(PDO::FETCH_ASSOC);
        };

        $generate = static function (PDO $pdo, callable $slugify, callable $exists, string $name, int $id): string {
            $base = (string)$slugify($name);
            if ($base === '') {
                $base = 'uzivatel';
            }

            $slug = $base;
            $suffix = 2;
            while ($exists($pdo, $slug, $id)) {
                $slug = $base . '-' . $suffix;
                $suffix++;
            }

            return $slug;
        };

        foreach ($rows as $row) {
            $id = isset($row['id']) ? (int)$row['id'] : 0;
            if ($id <= 0) {
                continue;
            }

            $name = (string)($row['name'] ?? '');
            $slug = $generate($pdo, $slugify, $exists, $name, $id);

            $update = $pdo->prepare('UPDATE `users` SET `slug` = :slug WHERE `id` = :id');
            if ($update !== false) {
                $update->execute(['slug' => $slug, 'id' => $id]);
            }
        }

        $pdo->exec('ALTER TABLE `users` MODIFY `slug` VARCHAR(190) NOT NULL');

        try {
            $pdo->exec('CREATE UNIQUE INDEX `uq_users_slug` ON `users` (`slug`)');
        } catch (PDOException $exception) {
            if ((int)$exception->getCode() !== 1061) { // duplicate key name
                throw $exception;
            }
        }
    }

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $sql = sprintf('SHOW COLUMNS FROM `%s` LIKE :column', str_replace('`', '``', $table));
        $statement = $pdo->prepare($sql);
        if ($statement === false) {
            return false;
        }

        $statement->execute(['column' => $column]);
        return (bool)$statement->fetch(PDO::FETCH_ASSOC);
    }
}
