<?php
declare(strict_types=1);

use Core\Database\Migrations\Migration;

final class _20240601_0001_update_order_workflow extends Migration
{
    public function up(\PDO $pdo): void
    {
        $pdo->exec(
            "ALTER TABLE `orders` MODIFY `status` ENUM('draft','pending','processing','completed','cancelled','refunded','new','awaiting_payment','packed','shipped','delivered') NOT NULL DEFAULT 'new'"
        );

        $pdo->exec(
            "UPDATE `orders` SET `status` = CASE\n                WHEN `status` = 'draft' THEN 'new'\n                WHEN `status` = 'pending' THEN 'awaiting_payment'\n                WHEN `status` = 'processing' THEN 'packed'\n                WHEN `status` = 'completed' THEN 'delivered'\n                WHEN `status` = 'refunded' THEN 'cancelled'\n                ELSE `status`\n            END"
        );

        $pdo->exec(
            "ALTER TABLE `orders` MODIFY `status` ENUM('new','awaiting_payment','packed','shipped','delivered','cancelled') NOT NULL DEFAULT 'new'"
        );

        $pdo->exec(
            <<<SQL
CREATE TABLE IF NOT EXISTS `order_status_history` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `from_status` VARCHAR(60) NULL,
  `to_status` VARCHAR(60) NOT NULL,
  `note` TEXT NULL,
  `context` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `ix_order_status_history_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );

        $pdo->exec(
            <<<SQL
CREATE TABLE IF NOT EXISTS `order_shipments` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `carrier` VARCHAR(120) NULL,
  `tracking_number` VARCHAR(190) NULL,
  `shipped_at` DATETIME NOT NULL,
  `note` VARCHAR(190) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `ix_order_shipments_order` (`order_id`),
  INDEX `ix_order_shipments_tracking` (`tracking_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
    }
}
