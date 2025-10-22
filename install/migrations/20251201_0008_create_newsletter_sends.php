<?php
declare(strict_types=1);

use Core\Database\Migrations\Migration;

final class _20251201_0008_create_newsletter_sends extends Migration
{
    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS newsletter_sends (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            subject VARCHAR(255) NOT NULL,
            body MEDIUMTEXT NULL,
            recipients_count INT UNSIGNED NOT NULL DEFAULT 0,
            sent_count INT UNSIGNED NOT NULL DEFAULT 0,
            failed_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            KEY ix_newsletter_sends_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS newsletter_sends");
    }
}
