<?php
declare(strict_types=1);

use Core\Database\Migrations\Migration;

final class _20240530_0001_add_inventory_reservations extends Migration
{
    public function up(\PDO $pdo): void
    {
        $columnStmt = $pdo->query("SHOW COLUMNS FROM `product_variants` LIKE 'inventory_reserved'");
        $hasColumn = $columnStmt !== false && $columnStmt->fetch() !== false;
        if (!$hasColumn) {
            $pdo->exec("ALTER TABLE `product_variants` ADD COLUMN `inventory_reserved` INT NOT NULL DEFAULT 0 AFTER `inventory_quantity`");
        }

        $pdo->exec(
            <<<SQL
CREATE TABLE IF NOT EXISTS `stock_reservations` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `variant_id` BIGINT UNSIGNED NOT NULL,
  `quantity` INT NOT NULL,
  `state` ENUM('reserved','released','consumed') NOT NULL DEFAULT 'reserved',
  `reference` VARCHAR(190) NULL,
  `note` VARCHAR(190) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL,
  INDEX `ix_stock_reservations_order` (`order_id`),
  INDEX `ix_stock_reservations_variant` (`variant_id`),
  INDEX `ix_stock_reservations_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
    }
}
