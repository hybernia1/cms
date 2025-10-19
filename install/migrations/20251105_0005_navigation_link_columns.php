<?php
declare(strict_types=1);

use Core\Database\Migrations\Migration;

final class _20251105_0005_navigation_link_columns extends Migration
{
    public function up(\PDO $pdo): void
    {
        $columns = $this->columns($pdo);

        if (!isset($columns['link_type'])) {
            $pdo->exec("ALTER TABLE navigation_items ADD COLUMN link_type VARCHAR(50) NOT NULL DEFAULT 'custom' AFTER title");
        }

        if (!isset($columns['link_reference'])) {
            $pdo->exec("ALTER TABLE navigation_items ADD COLUMN link_reference VARCHAR(150) NULL AFTER link_type");
        }

        $pdo->exec("UPDATE navigation_items SET link_type = 'custom' WHERE link_type IS NULL OR link_type = ''");
    }

    public function down(\PDO $pdo): void
    {
        $columns = $this->columns($pdo);

        if (isset($columns['link_reference'])) {
            $pdo->exec("ALTER TABLE navigation_items DROP COLUMN link_reference");
        }

        if (isset($columns['link_type'])) {
            $pdo->exec("ALTER TABLE navigation_items DROP COLUMN link_type");
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function columns(\PDO $pdo): array
    {
        $stmt = $pdo->query("SHOW COLUMNS FROM navigation_items");
        if (!$stmt) {
            return [];
        }

        $columns = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            $field = $row['Field'] ?? null;
            if ($field === null) {
                continue;
            }
            $columns[(string)$field] = $row;
        }

        return $columns;
    }
}
