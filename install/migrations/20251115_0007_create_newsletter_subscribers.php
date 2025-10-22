<?php
declare(strict_types=1);

use Core\Database\Migrations\Migration;

final class _20251115_0007_create_newsletter_subscribers extends Migration
{
    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS newsletter_subscribers (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(191) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            source_url VARCHAR(255) NULL,
            confirm_token VARCHAR(64) NULL,
            confirm_expires_at DATETIME NULL,
            unsubscribe_token VARCHAR(64) NULL,
            created_at DATETIME NOT NULL,
            confirmed_at DATETIME NULL,
            unsubscribed_at DATETIME NULL,
            UNIQUE KEY uq_newsletter_email (email),
            KEY ix_newsletter_confirm_token (confirm_token),
            KEY ix_newsletter_unsubscribe_token (unsubscribe_token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS newsletter_subscribers");
    }
}
