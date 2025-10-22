<?php
declare(strict_types=1);

use Core\Database\Migrations\Migration;

final class _20251201_0008_cron_tables extends Migration
{
    public function up(PDO $pdo): void
    {
        $this->createCronTasksTable($pdo);
        $this->createCronLogTable($pdo);
        $this->ensureDebugTask($pdo);
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS cron_log');
        $pdo->exec('DROP TABLE IF EXISTS cron_tasks');
    }

    private function createCronTasksTable(PDO $pdo): void
    {
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS cron_tasks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hook VARCHAR(190) NOT NULL,
    args LONGTEXT NOT NULL,
    scheduled_at BIGINT UNSIGNED NOT NULL,
    interval_seconds INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    INDEX ix_cron_tasks_hook (hook),
    INDEX ix_cron_tasks_scheduled (scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL);
    }

    private function createCronLogTable(PDO $pdo): void
    {
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS cron_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id BIGINT UNSIGNED NULL,
    hook VARCHAR(190) NOT NULL,
    status ENUM('running','success','failure') NOT NULL,
    started_at BIGINT UNSIGNED NOT NULL,
    finished_at BIGINT UNSIGNED NULL,
    message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX ix_cron_log_task (task_id),
    INDEX ix_cron_log_hook (hook),
    INDEX ix_cron_log_status (status),
    INDEX ix_cron_log_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL);
    }

    private function ensureDebugTask(PDO $pdo): void
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM cron_tasks WHERE hook = :hook');
        $stmt->execute(['hook' => 'core/debug-heartbeat']);

        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $insert = $pdo->prepare('INSERT INTO cron_tasks (hook, args, scheduled_at, interval_seconds, created_at) VALUES (:hook, :args, :scheduled_at, :interval_seconds, NOW())');
        $insert->execute([
            'hook'             => 'core/debug-heartbeat',
            'args'             => '[]',
            'scheduled_at'     => time(),
            'interval_seconds' => 3600,
        ]);
    }
}
