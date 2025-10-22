<?php
declare(strict_types=1);

use Core\Database\Migrations\Migration;

final class _20251201_0008_cron_tables extends Migration
{
    public function up(\PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS cron_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                hook VARCHAR(190) NOT NULL,
                schedule VARCHAR(64) NULL,
                interval_seconds INT UNSIGNED NOT NULL DEFAULT 0,
                args JSON NULL,
                args_hash CHAR(32) NOT NULL,
                next_run DATETIME NULL,
                last_run DATETIME NULL,
                running TINYINT(1) NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                locked_until DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                INDEX ix_cron_events_hook (hook),
                INDEX ix_cron_events_next (next_run),
                INDEX ix_cron_events_active (active),
                UNIQUE KEY uq_cron_hook_hash (hook, args_hash)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS cron_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_id BIGINT UNSIGNED NULL,
                hook VARCHAR(190) NOT NULL,
                status ENUM('success','error','skipped') NOT NULL DEFAULT 'success',
                message TEXT NULL,
                started_at DATETIME NOT NULL,
                finished_at DATETIME NULL,
                duration_ms INT UNSIGNED NULL,
                context JSON NULL,
                INDEX ix_cron_logs_event (event_id),
                INDEX ix_cron_logs_hook (hook),
                INDEX ix_cron_logs_started (started_at),
                INDEX ix_cron_logs_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    public function down(\PDO $pdo): void
    {
        // Tabulky cronu ponecháme, protože obsahují provozní logy.
    }
}
