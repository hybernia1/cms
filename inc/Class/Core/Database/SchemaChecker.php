<?php
declare(strict_types=1);

namespace Core\Database;

final class SchemaChecker
{
    /**
     * @var array<string,bool>|null
     */
    private static ?array $tables = null;

    /**
     * @var null|(callable():iterable<int,string>)
     */
    private $tableFetcher;

    public function __construct(?callable $tableFetcher = null)
    {
        $this->tableFetcher = $tableFetcher;
    }

    public function preload(): void
    {
        $this->loadTables();
    }

    public function hasTable(string $table): bool
    {
        if ($table === '') {
            return false;
        }

        $tables = $this->loadTables();

        return isset($tables[$table]);
    }

    public static function resetCache(): void
    {
        self::$tables = null;
    }

    /**
     * @return array<string,bool>
     */
    private function loadTables(): array
    {
        if (self::$tables !== null) {
            return self::$tables;
        }

        $fetcher = $this->tableFetcher ?? [self::class, 'fetchTables'];
        /** @var iterable<int,string> $list */
        $list = $fetcher();

        $normalized = [];
        foreach ($list as $table) {
            if (!is_string($table)) {
                continue;
            }

            $name = trim($table);
            if ($name === '') {
                continue;
            }

            $normalized[$name] = true;
        }

        self::$tables = $normalized;

        return self::$tables;
    }

    /**
     * @return list<string>
     */
    private static function fetchTables(): array
    {
        $pdo = Init::pdo();
        $stmt = $pdo->query('SHOW TABLES');
        if ($stmt === false) {
            return [];
        }

        $tables = [];
        while (($table = $stmt->fetchColumn()) !== false) {
            if (!is_string($table)) {
                continue;
            }

            $name = trim($table);
            if ($name === '') {
                continue;
            }

            $tables[] = $name;
        }

        return $tables;
    }
}
