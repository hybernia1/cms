<?php
declare(strict_types=1);

namespace Core\Database\Migrations;

use Core\Database\Init;
use PDO;

final class Migrator
{
    /** @var string[] */
    private array $paths;

    public function __construct(array $paths)
    {
        $this->paths = $paths;
    }

    public function pdo(): PDO
    {
        return Init::pdo();
    }

    public function ensureMigrationsTable(): void
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS migrations (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL UNIQUE,
            batch INT UNSIGNED NOT NULL,
            ran_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->pdo()->exec($sql);
    }

    /** @return array<int,Migration> (v pořadí podle názvu souboru) */
    public function discover(): array
    {
        $classes = [];
        foreach ($this->paths as $dir) {
            if (!is_dir($dir)) { continue; }
            $files = glob(rtrim($dir,'/')."/*.php") ?: [];
            // stabilní řazení: nejprve podle názvu souboru
            sort($files, SORT_STRING);
            foreach ($files as $file) {
                require_once $file;
                // Hledej třídy v souboru
                $declared = get_declared_classes();
                // poslední třída v souboru bývá migrace, ale projdeme vše a najdeme potomky Migration
                foreach ($declared as $cls) {
                    if (is_subclass_of($cls, Migration::class)) {
                        $rf = new \ReflectionClass($cls);
                        if ($rf->getFileName() === realpath($file)) {
                            $classes[$cls] = new $cls();
                        }
                    }
                }
            }
        }
        // vrátíme pouze instance, setříděné podle name()
        $list = array_values($classes);
        usort($list, fn($a,$b)=>strcmp($a->name(), $b->name()));
        return $list;
    }

    /** @return array<string,bool> mapa již aplikovaných migrací (name => true) */
    public function appliedMap(): array
    {
        $this->ensureMigrationsTable();
        $stmt = $this->pdo()->query("SELECT name FROM migrations");
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(string)$row['name']] = true;
        }
        return $out;
    }

    /** Vrátí pole čekajících migrací (instance), v pořadí spuštění */
    public function pending(): array
    {
        $all = $this->discover();
        $applied = $this->appliedMap();
        return array_values(array_filter($all, fn(Migration $m)=>!isset($applied[$m->name()])));
    }

    /** Vrátí číslo dalšího batch (1 + max batch) */
    private function nextBatch(): int
    {
        $row = $this->pdo()->query("SELECT COALESCE(MAX(batch),0) AS b FROM migrations")->fetch(PDO::FETCH_ASSOC);
        return (int)($row['b'] ?? 0) + 1;
    }

    /** Spustí všechny čekající migrace (v jedné transakci, pokud lze). Vrátí počet. */
    public function runPending(): int
    {
        $pending = $this->pending();
        if (!$pending) return 0;

        $batch = $this->nextBatch();
        $pdo = $this->pdo();

        $commit = false;
        if (!$pdo->inTransaction()) { $pdo->beginTransaction(); $commit = true; }

        try {
            foreach ($pending as $m) {
                $m->up($pdo);
                $this->recordApplied($m->name(), $batch);
            }
            if ($commit) $pdo->commit();
            return count($pending);
        } catch (\Throwable $e) {
            if ($commit) $pdo->rollBack();
            throw $e;
        }
    }

    /** Smaže záznam o migraci (interně pro rollback) */
    private function forget(string $name): void
    {
        $st = $this->pdo()->prepare("DELETE FROM migrations WHERE name = ?");
        $st->execute([$name]);
    }

    /** Zapíše, že migrace byla aplikována */
    private function recordApplied(string $name, int $batch): void
    {
        $st = $this->pdo()->prepare("INSERT INTO migrations (name,batch,ran_at) VALUES (?,?,?)");
        $st->execute([$name, $batch, date('Y-m-d H:i:s')]);
    }

    /** Vrátí všechny aplikované migrace posledního batchu v DESC pořadí (pro rollback) */
    private function lastBatchApplied(): array
    {
        $st = $this->pdo()->query("SELECT batch FROM migrations ORDER BY batch DESC LIMIT 1");
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) return [];
        $batch = (int)$row['batch'];

        $st = $this->pdo()->prepare("SELECT name FROM migrations WHERE batch = ? ORDER BY id DESC");
        $st->execute([$batch]);
        return $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /** Rollbackne poslední batch (volá down v opačném pořadí). Vrátí počet. */
    public function rollbackLastBatch(): int
    {
        $names = $this->lastBatchApplied();
        if (!$names) return 0;

        // Znovu najdeme instance migrací
        $byName = [];
        foreach ($this->discover() as $m) { $byName[$m->name()] = $m; }

        $pdo = $this->pdo();
        $commit = false;
        if (!$pdo->inTransaction()) { $pdo->beginTransaction(); $commit = true; }

        try {
            foreach ($names as $name) {
                if (!isset($byName[$name])) continue; // chybějící soubor – přeskoč
                $byName[$name]->down($pdo);
                $this->forget($name);
            }
            if ($commit) $pdo->commit();
            return count($names);
        } catch (\Throwable $e) {
            if ($commit) $pdo->rollBack();
            throw $e;
        }
    }
}
