<?php
declare(strict_types=1);

namespace Core\Database\Migrations;

use PDO;

abstract class Migration
{
    /**
     * Jedinečný název migrace. Default = název souboru bez .php
     * Můžeš přepsat, ale běžně necháš výchozí.
     */
    public function name(): string
    {
        // pokus se získat ze Reflectionu soubor
        $rf = new \ReflectionClass($this);
        $file = basename((string)$rf->getFileName());
        return preg_replace('~\.php$~', '', $file) ?: $rf->getName();
    }

    /** Migrační UP – vytvoř tabulky/sloupce/data */
    abstract public function up(PDO $pdo): void;

    /** Migrační DOWN – revert (volitelné) */
    public function down(PDO $pdo): void
    {
        // volitelné – nemusí nic dělat
    }
}
