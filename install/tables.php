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
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  role ENUM('admin','editor','author','user') NOT NULL DEFAULT 'user',
  token VARCHAR(64) NULL,
  token_expire DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  INDEX ix_users_active (active),
  INDEX ix_users_role (role),
  INDEX ix_users_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
,

/** POSTS */
<<<SQL
CREATE TABLE IF NOT EXISTS posts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL UNIQUE,
  type VARCHAR(50) NOT NULL DEFAULT 'post',
  status ENUM('draft','publish') NOT NULL DEFAULT 'draft',
  content LONGTEXT NULL,
  author_id BIGINT UNSIGNED NOT NULL,
  thumbnail_id BIGINT UNSIGNED NULL,
  comments_allowed TINYINT(1) NOT NULL DEFAULT 1,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  INDEX ix_posts_type (type),
  INDEX ix_posts_status (status),
  INDEX ix_posts_author (author_id),
  INDEX ix_posts_published (published_at),
  INDEX ix_posts_created (created_at)
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

/** POST_MEDIA (N:M vazby, role např. "gallery","attachment","hero") */
<<<SQL
CREATE TABLE IF NOT EXISTS post_media (
  post_id BIGINT UNSIGNED NOT NULL,
  media_id BIGINT UNSIGNED NOT NULL,
  role VARCHAR(50) NOT NULL DEFAULT 'attachment',
  PRIMARY KEY (post_id, media_id),
  INDEX ix_pm_role (role),
  INDEX ix_pm_media (media_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
,

/** TERMS */
<<<SQL
CREATE TABLE IF NOT EXISTS terms (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(50) NOT NULL DEFAULT 'tag',  -- "category","tag", do budoucna cokoliv
  slug VARCHAR(190) NOT NULL UNIQUE,
  name VARCHAR(190) NOT NULL,
  description TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX ix_terms_type (type),
  INDEX ix_terms_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
,

/** POST_TERMS (N:M) */
<<<SQL
CREATE TABLE IF NOT EXISTS post_terms (
  post_id BIGINT UNSIGNED NOT NULL,
  term_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (post_id, term_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  INDEX ix_nav_menus_location (location)
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

/** COMMENTS (anonymní i registrovaní, parent pro vlákna) */
<<<SQL
CREATE TABLE IF NOT EXISTS comments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  parent_id BIGINT UNSIGNED NULL,
  author_name VARCHAR(150) NULL,
  author_email VARCHAR(190) NULL,
  content TEXT NOT NULL,
  status ENUM('draft','published','spam','trash') NOT NULL DEFAULT 'published',
  ip VARCHAR(45) NULL,
  ua VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  INDEX ix_comments_post (post_id),
  INDEX ix_comments_parent (parent_id),
  INDEX ix_comments_status (status),
  INDEX ix_comments_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
,

];
