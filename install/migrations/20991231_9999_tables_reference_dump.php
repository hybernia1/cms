<?php
declare(strict_types=1);

use Core\Database\Migrations\Migration;

/**
 * Dummy migrace sloužící pouze jako reference k install/tables.php.
 * Při spuštění vypíše jednotlivé SQL příkazy do error logu, aby
 * měl tým přehled, co tables.php vytváří.
 */
final class _20991231_9999_tables_reference_dump extends Migration
{
    public function up(\PDO $pdo): void
    {
        $tablesFile = __DIR__ . '/../tables.php';
        if (!is_file($tablesFile)) {
            error_log('install/tables.php nebyl nalezen.');
            return;
        }

        /** @var array<int, string> $sqls */
        $sqls = require $tablesFile;
        foreach ($sqls as $sql) {
            if (!is_string($sql)) {
                continue;
            }
            $normalized = preg_replace('~\s+~', ' ', $sql);
            if ($normalized === null) {
                $normalized = $sql;
            }
            $statement = trim($normalized);
            if ($statement === '') {
                continue;
            }
            error_log('[tables.php] ' . $statement);
        }
    }
}
