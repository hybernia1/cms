<?php
declare(strict_types=1);

use Core\Database\Migrations\Migration;

final class _20240520_0001_create_commerce_tables extends Migration
{
    public function up(\PDO $pdo): void
    {
        $statements = [
            <<<SQL
CREATE TABLE IF NOT EXISTS products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(190) NOT NULL,
  name VARCHAR(190) NOT NULL,
  description LONGTEXT NULL,
  short_description TEXT NULL,
  status ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  tax_class VARCHAR(64) NULL,
  meta JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  published_at DATETIME NULL,
  UNIQUE KEY uq_products_slug (slug),
  INDEX ix_products_status (status),
  INDEX ix_products_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS customers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  email VARCHAR(190) NOT NULL,
  first_name VARCHAR(150) NOT NULL,
  last_name VARCHAR(150) NOT NULL,
  phone VARCHAR(50) NULL,
  marketing_opt_in TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_customers_email (email),
  INDEX ix_customers_user (user_id),
  INDEX ix_customers_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS product_variants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  sku VARCHAR(120) NOT NULL,
  name VARCHAR(190) NOT NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  compare_at_price DECIMAL(12,2) NULL,
  cost DECIMAL(12,2) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  inventory_quantity INT NOT NULL DEFAULT 0,
  inventory_reserved INT NOT NULL DEFAULT 0,
  track_inventory TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  weight DECIMAL(10,3) NULL,
  length DECIMAL(10,3) NULL,
  width DECIMAL(10,3) NULL,
  height DECIMAL(10,3) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_product_variants_sku (sku),
  INDEX ix_product_variants_product (product_id),
  INDEX ix_product_variants_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS product_attributes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(190) NOT NULL,
  name VARCHAR(190) NOT NULL,
  type ENUM('text','number','select') NOT NULL DEFAULT 'text',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_product_attributes_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS product_variant_attributes (
  variant_id BIGINT UNSIGNED NOT NULL,
  attribute_id BIGINT UNSIGNED NOT NULL,
  value VARCHAR(255) NULL,
  PRIMARY KEY (variant_id, attribute_id),
  INDEX ix_pva_attribute (attribute_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id BIGINT UNSIGNED NULL,
  slug VARCHAR(190) NOT NULL,
  name VARCHAR(190) NOT NULL,
  description TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_categories_slug (slug),
  INDEX ix_categories_parent (parent_id),
  INDEX ix_categories_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS category_product (
  product_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (product_id, category_id),
  INDEX ix_category_product_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS stock_entries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  variant_id BIGINT UNSIGNED NOT NULL,
  quantity_change INT NOT NULL,
  reason VARCHAR(100) NULL,
  reference VARCHAR(190) NULL,
  meta JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX ix_stock_entries_variant (variant_id),
  INDEX ix_stock_entries_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS stock_reservations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  variant_id BIGINT UNSIGNED NOT NULL,
  quantity INT NOT NULL,
  state ENUM('reserved','released','consumed') NOT NULL DEFAULT 'reserved',
  reference VARCHAR(190) NULL,
  note VARCHAR(190) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  INDEX ix_stock_reservations_order (order_id),
  INDEX ix_stock_reservations_variant (variant_id),
  INDEX ix_stock_reservations_state (state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(190) NOT NULL,
  customer_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL,
  status ENUM('new','awaiting_payment','packed','shipped','delivered','cancelled') NOT NULL DEFAULT 'new',
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  shipping_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  placed_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_orders_number (order_number),
  INDEX ix_orders_status (status),
  INDEX ix_orders_user (user_id),
  INDEX ix_orders_customer (customer_id),
  INDEX ix_orders_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  variant_id BIGINT UNSIGNED NULL,
  sku VARCHAR(120) NULL,
  name VARCHAR(190) NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  meta JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX ix_order_items_order (order_id),
  INDEX ix_order_items_product (product_id),
  INDEX ix_order_items_variant (variant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS order_status_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  from_status VARCHAR(60) NULL,
  to_status VARCHAR(60) NOT NULL,
  note TEXT NULL,
  context JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX ix_order_status_history_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS order_shipments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  carrier VARCHAR(120) NULL,
  tracking_number VARCHAR(190) NULL,
  shipped_at DATETIME NOT NULL,
  note VARCHAR(190) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX ix_order_shipments_order (order_id),
  INDEX ix_order_shipments_tracking (tracking_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS addresses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  type ENUM('billing','shipping') NOT NULL DEFAULT 'billing',
  first_name VARCHAR(150) NOT NULL,
  last_name VARCHAR(150) NOT NULL,
  company VARCHAR(190) NULL,
  line1 VARCHAR(190) NOT NULL,
  line2 VARCHAR(190) NULL,
  city VARCHAR(150) NOT NULL,
  state VARCHAR(150) NULL,
  postal_code VARCHAR(30) NOT NULL,
  country VARCHAR(2) NOT NULL,
  phone VARCHAR(50) NULL,
  email VARCHAR(190) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  INDEX ix_addresses_order (order_id),
  INDEX ix_addresses_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
            <<<SQL
INSERT INTO categories (slug, name, description, sort_order, created_at)
SELECT 'general', 'General', 'Default product category', 1, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM categories WHERE slug = 'general'
);
SQL,
            <<<SQL
INSERT INTO product_attributes (slug, name, type, created_at)
SELECT 'color', 'Color', 'select', NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM product_attributes WHERE slug = 'color'
);
SQL,
        ];

        foreach ($statements as $sql) {
            $pdo->exec($sql);
        }

        $legacy = [
            'post_terms',
            'post_media',
            'comments',
            'terms',
            'posts',
        ];

        foreach ($legacy as $table) {
            $pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }
    }
}
