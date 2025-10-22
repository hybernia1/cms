<?php
declare(strict_types=1);

use Core\Database\Migrations\Migration;

final class _20251215_0009_create_newsletter_campaign_tables extends Migration
{
    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS newsletter_campaigns (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            subject VARCHAR(255) NOT NULL,
            body MEDIUMTEXT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'draft',
            recipients_count INT UNSIGNED NOT NULL DEFAULT 0,
            sent_count INT UNSIGNED NOT NULL DEFAULT 0,
            failed_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            sent_at DATETIME NULL,
            KEY ix_newsletter_campaigns_created (created_at),
            KEY ix_newsletter_campaigns_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS newsletter_campaign_schedules (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            campaign_id INT UNSIGNED NOT NULL,
            scheduled_for DATETIME NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL,
            processed_at DATETIME NULL,
            KEY ix_newsletter_campaign_schedules_campaign (campaign_id),
            KEY ix_newsletter_campaign_schedules_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS newsletter_campaign_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            campaign_id INT UNSIGNED NOT NULL,
            subscriber_id INT UNSIGNED NOT NULL,
            status VARCHAR(50) NOT NULL,
            response TEXT NULL,
            error TEXT NULL,
            sent_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            KEY ix_newsletter_campaign_logs_campaign (campaign_id),
            KEY ix_newsletter_campaign_logs_subscriber (subscriber_id),
            KEY ix_newsletter_campaign_logs_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS newsletter_campaign_logs");
        $pdo->exec("DROP TABLE IF EXISTS newsletter_campaign_schedules");
        $pdo->exec("DROP TABLE IF EXISTS newsletter_campaigns");
    }
}
