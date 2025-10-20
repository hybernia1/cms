<?php
declare(strict_types=1);

use Core\Database\Migrations\Migration;

final class _20251110_0006_navigation_location_unique extends Migration
{
    public function up(\PDO $pdo): void
    {
        if (!$this->indexExists($pdo, 'navigation_menus', 'uq_nav_menus_location')) {
            if ($this->indexExists($pdo, 'navigation_menus', 'ix_nav_menus_location')) {
                $pdo->exec("ALTER TABLE navigation_menus DROP INDEX ix_nav_menus_location");
            }
            $pdo->exec("ALTER TABLE navigation_menus ADD UNIQUE INDEX uq_nav_menus_location (location)");
        }
    }

    public function down(\PDO $pdo): void
    {
        if ($this->indexExists($pdo, 'navigation_menus', 'uq_nav_menus_location')) {
            $pdo->exec("ALTER TABLE navigation_menus DROP INDEX uq_nav_menus_location");
            if (!$this->indexExists($pdo, 'navigation_menus', 'ix_nav_menus_location')) {
                $pdo->exec("ALTER TABLE navigation_menus ADD INDEX ix_nav_menus_location (location)");
            }
        }
    }

    private function indexExists(\PDO $pdo, string $table, string $index): bool
    {
        $stmt = $pdo->prepare("SHOW INDEX FROM {$table} WHERE Key_name = ?");
        $stmt->execute([$index]);
        return (bool)$stmt->fetch();
    }
}
