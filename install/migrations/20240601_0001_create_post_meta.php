<?php
declare(strict_types=1);

use Core\Database\Migrations\Migration;
use PDO;

final class _20240601_0001_create_post_meta extends Migration
{
    public function up(PDO $pdo): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS post_meta (
  post_id BIGINT UNSIGNED NOT NULL,
  meta_key VARCHAR(191) NOT NULL,
  meta_type VARCHAR(50) NOT NULL DEFAULT 'string',
  meta_value LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (post_id, meta_key),
  INDEX ix_post_meta_key (meta_key),
  INDEX ix_post_meta_type (meta_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

        $pdo->exec($sql);
    }
}
