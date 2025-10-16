<?php
declare(strict_types=1);

use Core\Database\Migrations\Migration;

final class _20251016_0001_create_settings_table extends Migration
{
    public function up(\PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS settings (
                id INT UNSIGNED NOT NULL PRIMARY KEY,
                site_title VARCHAR(255) NOT NULL DEFAULT 'Moje stránka',
                site_email VARCHAR(255) NOT NULL DEFAULT '',
                theme_slug VARCHAR(64) NOT NULL DEFAULT 'classic',
                created_at DATETIME NULL,
                updated_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $stmt = $pdo->prepare("SELECT 1 FROM settings WHERE id=1");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $ins = $pdo->prepare("INSERT INTO settings (id,site_title,site_email,theme_slug,created_at,updated_at) VALUES (1,'Moje stránka','', 'classic', NOW(), NOW())");
            $ins->execute();
        }
    }

    public function down(\PDO $pdo): void
    {
        // necháme prázdné (v produkci tabulku obvykle nerušíme)
        // $pdo->exec("DROP TABLE IF EXISTS settings");
    }
}
