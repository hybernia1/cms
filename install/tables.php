<?php
declare(strict_types=1);

/**
 * tables.php
 * ----------
 * Exportuje pole SQL příkazů (CREATE TABLE IF NOT EXISTS ...).
 * InnoDB + utf8mb4, bez cizích klíčů kvůli kompatibilitě (jen indexy).
 */

return [

/** USERS */
<<<SQL
CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  website_url VARCHAR(255) NULL,
  avatar_path VARCHAR(255) NULL,
  bio TEXT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  role ENUM('admin','editor','author','user') NOT NULL DEFAULT 'user',
  token VARCHAR(64) NULL,
  token_expire DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_users_slug (slug),
  INDEX ix_users_active (active),
  INDEX ix_users_role (role),
  INDEX ix_users_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
,

/** MEDIA */
<<<SQL
CREATE TABLE IF NOT EXISTS media (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(50) NOT NULL DEFAULT 'image',
  mime VARCHAR(100) NOT NULL,
  url VARCHAR(500) NOT NULL,        -- veřejná URL (můžeš si přepnout na relativní)
  rel_path VARCHAR(500) NULL,       -- volitelně relativní path v /uploads
  meta JSON NULL,                   -- volitelná metadata (šířka/výška apod.)
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX ix_media_user (user_id),
  INDEX ix_media_type (type),
  INDEX ix_media_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
,

/** PRODUCTS */
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
SQL
,

/** CUSTOMERS */
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
SQL
,

/** PRODUCT VARIANTS */
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
SQL
,

/** PRODUCT ATTRIBUTES */
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
SQL
,

/** PRODUCT VARIANT ATTRIBUTE VALUES */
<<<SQL
CREATE TABLE IF NOT EXISTS product_variant_attributes (
  variant_id BIGINT UNSIGNED NOT NULL,
  attribute_id BIGINT UNSIGNED NOT NULL,
  value VARCHAR(255) NULL,
  PRIMARY KEY (variant_id, attribute_id),
  INDEX ix_pva_attribute (attribute_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
,

/** CATEGORIES */
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
SQL
,

/** CATEGORY ↔ PRODUCT PIVOT */
<<<SQL
CREATE TABLE IF NOT EXISTS category_product (
  product_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (product_id, category_id),
  INDEX ix_category_product_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
,

/** STOCK ENTRIES */
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
SQL
,

/** ORDERS */
<<<SQL
CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(190) NOT NULL,
  customer_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL,
  status ENUM('draft','pending','processing','completed','cancelled','refunded') NOT NULL DEFAULT 'pending',
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
SQL
,

/** ORDER ITEMS */
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
SQL
,

/** ORDER ADDRESSES */
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
SQL
,

/** DEFAULT CATEGORY */
<<<SQL
INSERT INTO categories (slug, name, description, sort_order, created_at)
SELECT 'general', 'General', 'Default product category', 1, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM categories WHERE slug = 'general'
);
SQL
,

/** DEFAULT PRODUCT ATTRIBUTE */
<<<SQL
INSERT INTO product_attributes (slug, name, type, created_at)
SELECT 'color', 'Color', 'select', NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM product_attributes WHERE slug = 'color'
);
SQL
,

/** SETTINGS (single-row + JSON data pro flexibilitu) */
<<<SQL
CREATE TABLE IF NOT EXISTS settings (
  id TINYINT UNSIGNED NOT NULL DEFAULT 1 PRIMARY KEY,
  site_title VARCHAR(190) NOT NULL DEFAULT 'Moje stránka',
  site_email VARCHAR(190) NOT NULL DEFAULT '',
  theme_slug VARCHAR(64) NOT NULL DEFAULT 'classic',
  date_format VARCHAR(64) NOT NULL DEFAULT 'Y-m-d',
  time_format VARCHAR(64) NOT NULL DEFAULT 'H:i',
  timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Prague',
  allow_registration TINYINT(1) NOT NULL DEFAULT 1,
  registration_auto_approve TINYINT(1) NOT NULL DEFAULT 1,
  site_url VARCHAR(255) NOT NULL DEFAULT '',
  data JSON NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
,

/** PLUGIN SETTINGS */
<<<SQL
CREATE TABLE IF NOT EXISTS plugin_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(190) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  options JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_plugin_settings_slug (slug),
  INDEX ix_plugin_settings_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
,

/** WIDGET SETTINGS */
<<<SQL
CREATE TABLE IF NOT EXISTS widget_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  widget_id VARCHAR(190) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  options JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_widget_settings_id (widget_id),
  INDEX ix_widget_settings_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
,

/** NAVIGATION MENUS */
<<<SQL
CREATE TABLE IF NOT EXISTS navigation_menus (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(64) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  location VARCHAR(64) NOT NULL DEFAULT 'primary',
  description TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_nav_menus_location (location)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
,

/** NAVIGATION ITEMS */
<<<SQL
CREATE TABLE IF NOT EXISTS navigation_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  menu_id BIGINT UNSIGNED NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  title VARCHAR(150) NOT NULL,
  link_type VARCHAR(50) NOT NULL DEFAULT 'custom',
  link_reference VARCHAR(150) NULL,
  url VARCHAR(500) NOT NULL,
  target VARCHAR(20) NOT NULL DEFAULT '_self',
  css_class VARCHAR(150) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  INDEX ix_nav_items_menu (menu_id),
  INDEX ix_nav_items_parent (parent_id),
  INDEX ix_nav_items_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
,

/** DEFAULT NAVIGATION MENU */
<<<SQL
INSERT INTO navigation_menus (slug, name, location, description, created_at)
SELECT 'primary', 'Hlavní menu', 'primary', 'Výchozí navigace', NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM navigation_menus WHERE slug = 'primary'
);
SQL
,

/** DEFAULT NAVIGATION ITEMS */
<<<SQL
INSERT INTO navigation_items (menu_id, parent_id, title, link_type, link_reference, url, target, css_class, sort_order, created_at)
SELECT m.id, NULL, 'Index', 'route', 'home', '/', '_self', NULL, 1, NOW()
FROM navigation_menus m
WHERE m.slug = 'primary'
  AND NOT EXISTS (
      SELECT 1 FROM navigation_items ni
      WHERE ni.menu_id = m.id AND ni.title = 'Index' AND ni.parent_id IS NULL
  );
SQL
,
<<<SQL
INSERT INTO navigation_items (menu_id, parent_id, title, link_type, link_reference, url, target, css_class, sort_order, created_at)
SELECT m.id, NULL, 'Admin', 'route', 'admin', '/admin', '_self', NULL, 2, NOW()
FROM navigation_menus m
WHERE m.slug = 'primary'
  AND NOT EXISTS (
      SELECT 1 FROM navigation_items ni
      WHERE ni.menu_id = m.id AND ni.title = 'Admin' AND ni.parent_id IS NULL
  );
SQL
,
<<<SQL
INSERT INTO navigation_items (menu_id, parent_id, title, link_type, link_reference, url, target, css_class, sort_order, created_at)
SELECT m.id, NULL, 'Register', 'route', 'register', '/register', '_self', NULL, 3, NOW()
FROM navigation_menus m
WHERE m.slug = 'primary'
  AND NOT EXISTS (
      SELECT 1 FROM navigation_items ni
      WHERE ni.menu_id = m.id AND ni.title = 'Register' AND ni.parent_id IS NULL
  );
SQL
,
<<<SQL
INSERT INTO navigation_items (menu_id, parent_id, title, link_type, link_reference, url, target, css_class, sort_order, created_at)
SELECT m.id, NULL, 'Login', 'route', 'login', '/login', '_self', NULL, 4, NOW()
FROM navigation_menus m
WHERE m.slug = 'primary'
  AND NOT EXISTS (
      SELECT 1 FROM navigation_items ni
      WHERE ni.menu_id = m.id AND ni.title = 'Login' AND ni.parent_id IS NULL
  );
SQL
,
<<<SQL
INSERT INTO navigation_items (menu_id, parent_id, title, link_type, link_reference, url, target, css_class, sort_order, created_at)
SELECT m.id, NULL, 'Search', 'route', 'search', '/search', '_self', NULL, 5, NOW()
FROM navigation_menus m
WHERE m.slug = 'primary'
  AND NOT EXISTS (
      SELECT 1 FROM navigation_items ni
      WHERE ni.menu_id = m.id AND ni.title = 'Search' AND ni.parent_id IS NULL
  );
SQL
,

];
