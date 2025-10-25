<?php
declare(strict_types=1);

use Core\Database\Migrations\Migration;

final class _20240514_0001_add_plugin_widget_settings extends Migration
{
    public function up(PDO $pdo): void
    {
        $this->createPluginSettingsTable($pdo);
        $this->createWidgetSettingsTable($pdo);
    }

    private function createPluginSettingsTable(PDO $pdo): void
    {
        if ($this->tableExists($pdo, 'plugin_settings')) {
            return;
        }

        $sql = <<<SQL
CREATE TABLE `plugin_settings` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(190) NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `options` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL,
  UNIQUE KEY `uq_plugin_settings_slug` (`slug`),
  INDEX `ix_plugin_settings_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

        $pdo->exec($sql);
    }

    private function createWidgetSettingsTable(PDO $pdo): void
    {
        if ($this->tableExists($pdo, 'widget_settings')) {
            return;
        }

        $sql = <<<SQL
CREATE TABLE `widget_settings` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `widget_id` VARCHAR(190) NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `options` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL,
  UNIQUE KEY `uq_widget_settings_id` (`widget_id`),
  INDEX `ix_widget_settings_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

        $pdo->exec($sql);
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $table = trim($table);
        if ($table === '') {
            return false;
        }

        $sql = 'SELECT 1 FROM INFORMATION_SCHEMA.TABLES'
            . ' WHERE TABLE_SCHEMA = DATABASE()'
            . ' AND TABLE_NAME = :table'
            . ' LIMIT 1';

        $statement = $pdo->prepare($sql);
        if ($statement === false) {
            return false;
        }

        $statement->execute(['table' => $table]);

        return (bool) $statement->fetch(PDO::FETCH_NUM);
    }
}
