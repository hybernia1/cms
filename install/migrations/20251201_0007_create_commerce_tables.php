<?php
declare(strict_types=1);

use Core\Database\Migrations\Migration;

final class _20251201_0007_create_commerce_tables extends Migration
{
    public function up(\PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            parent_id BIGINT UNSIGNED NULL,
            name VARCHAR(190) NOT NULL,
            slug VARCHAR(190) NOT NULL,
            description TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            deleted_at DATETIME NULL,
            UNIQUE KEY uq_categories_slug (slug),
            INDEX ix_categories_parent (parent_id),
            INDEX ix_categories_deleted (deleted_at),
            CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS products (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            category_id BIGINT UNSIGNED NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            sku VARCHAR(100) NULL,
            summary TEXT NULL,
            description LONGTEXT NULL,
            status ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
            type ENUM('simple','variable','bundle') NOT NULL DEFAULT 'simple',
            price DECIMAL(12,2) NULL,
            currency CHAR(3) NOT NULL DEFAULT 'CZK',
            weight DECIMAL(10,2) NULL,
            dimensions JSON NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            deleted_at DATETIME NULL,
            UNIQUE KEY uq_products_slug (slug),
            UNIQUE KEY uq_products_sku (sku),
            INDEX ix_products_status (status),
            INDEX ix_products_category (category_id),
            INDEX ix_products_deleted (deleted_at),
            CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS product_variants (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT UNSIGNED NOT NULL,
            sku VARCHAR(100) NOT NULL,
            name VARCHAR(255) NULL,
            price DECIMAL(12,2) NOT NULL,
            currency CHAR(3) NOT NULL DEFAULT 'CZK',
            position INT NOT NULL DEFAULT 0,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            deleted_at DATETIME NULL,
            UNIQUE KEY uq_product_variants_sku (sku),
            INDEX ix_product_variants_product (product_id),
            INDEX ix_product_variants_status (status),
            INDEX ix_product_variants_deleted (deleted_at),
            CONSTRAINT fk_product_variants_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS product_attributes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT UNSIGNED NULL,
            variant_id BIGINT UNSIGNED NULL,
            attribute_key VARCHAR(190) NOT NULL,
            attribute_value VARCHAR(255) NOT NULL,
            position INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            INDEX ix_product_attributes_product (product_id),
            INDEX ix_product_attributes_variant (variant_id),
            INDEX ix_product_attributes_key (attribute_key),
            CONSTRAINT fk_product_attributes_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            CONSTRAINT fk_product_attributes_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS inventory_locations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(190) NOT NULL,
            code VARCHAR(64) NOT NULL,
            description TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            deleted_at DATETIME NULL,
            UNIQUE KEY uq_inventory_locations_code (code),
            INDEX ix_inventory_locations_deleted (deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS inventory_movements (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            variant_id BIGINT UNSIGNED NOT NULL,
            location_id BIGINT UNSIGNED NOT NULL,
            quantity INT NOT NULL,
            movement_type ENUM('adjustment','sale','restock','transfer') NOT NULL DEFAULT 'adjustment',
            reference VARCHAR(190) NULL,
            meta JSON NULL,
            occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX ix_inventory_movements_variant (variant_id),
            INDEX ix_inventory_movements_location (location_id),
            INDEX ix_inventory_movements_type (movement_type),
            INDEX ix_inventory_movements_occurred (occurred_at),
            CONSTRAINT fk_inventory_movements_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
            CONSTRAINT fk_inventory_movements_location FOREIGN KEY (location_id) REFERENCES inventory_locations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            email VARCHAR(190) NOT NULL,
            first_name VARCHAR(150) NULL,
            last_name VARCHAR(150) NULL,
            phone VARCHAR(50) NULL,
            marketing_opt_in TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            deleted_at DATETIME NULL,
            UNIQUE KEY uq_customers_email (email),
            UNIQUE KEY uq_customers_user (user_id),
            INDEX ix_customers_deleted (deleted_at),
            CONSTRAINT fk_customers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS order_statuses (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) NOT NULL,
            label VARCHAR(100) NOT NULL,
            is_final TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            UNIQUE KEY uq_order_statuses_code (code),
            INDEX ix_order_statuses_final (is_final)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(50) NOT NULL,
            customer_id BIGINT UNSIGNED NULL,
            status_id BIGINT UNSIGNED NOT NULL,
            currency CHAR(3) NOT NULL DEFAULT 'CZK',
            subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
            discount_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            tax_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            shipping_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            total DECIMAL(12,2) NOT NULL DEFAULT 0,
            notes TEXT NULL,
            placed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            paid_at DATETIME NULL,
            cancelled_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            deleted_at DATETIME NULL,
            UNIQUE KEY uq_orders_number (order_number),
            INDEX ix_orders_customer (customer_id),
            INDEX ix_orders_status (status_id),
            INDEX ix_orders_deleted (deleted_at),
            CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
            CONSTRAINT fk_orders_status FOREIGN KEY (status_id) REFERENCES order_statuses(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS addresses (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id BIGINT UNSIGNED NULL,
            order_id BIGINT UNSIGNED NULL,
            type ENUM('billing','shipping','other') NOT NULL DEFAULT 'billing',
            name VARCHAR(190) NULL,
            company VARCHAR(190) NULL,
            phone VARCHAR(50) NULL,
            line1 VARCHAR(190) NOT NULL,
            line2 VARCHAR(190) NULL,
            city VARCHAR(190) NOT NULL,
            postal_code VARCHAR(50) NOT NULL,
            region VARCHAR(190) NULL,
            country_code CHAR(2) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            INDEX ix_addresses_customer (customer_id),
            INDEX ix_addresses_order (order_id),
            CONSTRAINT fk_addresses_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            CONSTRAINT fk_addresses_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NULL,
            variant_id BIGINT UNSIGNED NULL,
            quantity INT NOT NULL DEFAULT 1,
            unit_price DECIMAL(12,2) NOT NULL,
            discount_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            tax_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            total DECIMAL(12,2) NOT NULL DEFAULT 0,
            name VARCHAR(255) NOT NULL,
            sku VARCHAR(100) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            INDEX ix_order_items_order (order_id),
            INDEX ix_order_items_product (product_id),
            INDEX ix_order_items_variant (variant_id),
            CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
            CONSTRAINT fk_order_items_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS invoices (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT UNSIGNED NOT NULL,
            invoice_number VARCHAR(50) NOT NULL,
            status ENUM('draft','issued','paid','void') NOT NULL DEFAULT 'draft',
            total DECIMAL(12,2) NOT NULL DEFAULT 0,
            currency CHAR(3) NOT NULL DEFAULT 'CZK',
            issued_at DATETIME NULL,
            due_at DATETIME NULL,
            paid_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            UNIQUE KEY uq_invoices_number (invoice_number),
            UNIQUE KEY uq_invoices_order (order_id),
            INDEX ix_invoices_status (status),
            CONSTRAINT fk_invoices_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->seedOrderStatuses($pdo);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ([
            'invoices',
            'order_items',
            'orders',
            'order_statuses',
            'addresses',
            'customers',
            'inventory_movements',
            'inventory_locations',
            'product_attributes',
            'product_variants',
            'products',
            'categories'
        ] as $table) {
            $pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function seedOrderStatuses(\PDO $pdo): void
    {
        $stmt = $pdo->query('SELECT COUNT(*) FROM order_statuses');
        if ($stmt && (int) $stmt->fetchColumn() === 0) {
            $now = date('Y-m-d H:i:s');
            $insert = $pdo->prepare('INSERT INTO order_statuses (code,label,is_final,sort_order,created_at) VALUES (?,?,?,?,?)');
            foreach ([
                ['pending', 'Čeká na platbu', 0, 10],
                ['processing', 'Zpracovává se', 0, 20],
                ['shipped', 'Odesláno', 0, 30],
                ['completed', 'Dokončeno', 1, 40],
                ['cancelled', 'Zrušeno', 1, 50],
            ] as [$code, $label, $final, $order]) {
                $insert->execute([$code, $label, $final, $order, $now]);
            }
        }
    }
}
