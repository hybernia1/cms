<?php
declare(strict_types=1);

namespace Core\Database;

use PDO;
use PDOException;

final class Connect
{
    private readonly array $cfg;
    private ?PDO $pdo = null;

    public function __construct(array $config)
    {
        $this->cfg = $config['db'] ?? [];
        if (!$this->cfg) {
            throw new \RuntimeException('Database config missing (config["db"]).');
        }
        // Nic nevytvářím hned – lazy init v $this->pdo()
    }

    /**
     * Vrať živé PDO (lazy). Udržuje jedno připojení (per instance).
     */
    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $driver  = (string)($this->cfg['driver']   ?? 'mysql');
        $host    = (string)($this->cfg['host']     ?? 'localhost');
        $port    = (int)   ($this->cfg['port']     ?? 3306);
        $db      = (string)($this->cfg['database'] ?? '');
        $user    = (string)($this->cfg['user']     ?? '');
        $pass    = (string)($this->cfg['password'] ?? '');
        $charset = (string)($this->cfg['charset']  ?? 'utf8mb4');

        // DSN – MySQL/MariaDB
        $dsn = match ($driver) {
            'mysql' => "mysql:host={$host};port={$port};dbname={$db};charset={$charset}",
            default => throw new \RuntimeException("Unsupported DB driver: {$driver}")
        };

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // natvrdo – chceme reálné prepared statements
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);

            // MySQL doporučené režimy (bez ONLY_FULL_GROUP_BY, pokud ti překáží)
            $pdo->exec("SET NAMES {$charset}");
            $pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

            $this->pdo = $pdo;
            return $this->pdo;
        } catch (PDOException $e) {
            throw new \RuntimeException('DB connection failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Užitečný helper: transakční blok s rollbackem při výjimce.
     * @return mixed návratová hodnota callbacku
     */
    public function transactional(callable $fn): mixed
    {
        $pdo = $this->pdo();
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            try {
                $result = $fn($pdo);
                $pdo->commit();
                return $result;
            } catch (\Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
        // Pokud už jsme v transakci, prostě to proveď
        return $fn($pdo);
    }
}
