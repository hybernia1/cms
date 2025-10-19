<?php
declare(strict_types=1);

use Core\Database\Migrations\Migration;

final class _20251016_0004_navigation_tables extends Migration
{
    public function up(\PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS navigation_menus (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(64) NOT NULL UNIQUE,
            name VARCHAR(150) NOT NULL,
            location VARCHAR(64) NOT NULL DEFAULT 'primary',
            description TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            INDEX ix_nav_menus_location (location)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS navigation_items (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $primaryId = $this->ensurePrimaryMenu($pdo);
        $this->ensureDefaultItems($pdo, $primaryId);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS navigation_items");
        $pdo->exec("DROP TABLE IF EXISTS navigation_menus");
    }

    private function ensurePrimaryMenu(\PDO $pdo): int
    {
        $stmt = $pdo->prepare("SELECT id FROM navigation_menus WHERE slug = ? LIMIT 1");
        $stmt->execute(['primary']);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int)$id;
        }

        $insert = $pdo->prepare("INSERT INTO navigation_menus (slug,name,location,description,created_at) VALUES (?,?,?,?,NOW())");
        $insert->execute(['primary', 'Hlavní navigace', 'primary', 'Výchozí front-end menu']);
        return (int)$pdo->lastInsertId();
    }

    private function ensureDefaultItems(\PDO $pdo, int $menuId): void
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM navigation_items WHERE menu_id = ?");
        $stmt->execute([$menuId]);
        if ((int)$stmt->fetchColumn() > 0) {
            return;
        }

        $pdo->beginTransaction();
        try {
            $insert = $pdo->prepare("INSERT INTO navigation_items (menu_id,parent_id,title,link_type,link_reference,url,target,css_class,sort_order,created_at)
                VALUES (?,?,?,?,?,?,?,?,?,NOW())");

            $order = 0;
            foreach ([
                ['Domů', 'route', 'home', './', '_self'],
                ['Blog', 'custom', null, './type/post', '_self'],
                ['Stránky', 'custom', null, './type/page', '_self'],
                ['Termy', 'custom', null, './terms', '_self'],
            ] as [$title, $linkType, $linkRef, $url, $target]) {
                $insert->execute([$menuId, null, $title, $linkType, $linkRef, $url, $target, '', $order++]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
