<?php
declare(strict_types=1);

use Core\Database\Init;

if (!function_exists('db')) {
    /**
     * Retrieve the shared PDO instance bootstrapped through \Core\Database\Init.
     */
    function db(): \PDO
    {
        return Init::pdo();
    }
}

if (!function_exists('db_query')) {
    /**
     * Prepare and execute a database query returning the prepared statement.
     *
     * @param array<string,int|float|string|null> $params
     */
    function db_query(string $sql, array $params = []): \PDOStatement
    {
        $statement = db()->prepare($sql);
        if ($statement === false) {
            throw new \PDOException('Unable to prepare statement: ' . $sql);
        }

        foreach ($params as $key => $value) {
            $statement->bindValue(is_int($key) ? $key + 1 : (string)$key, $value);
        }

        $statement->execute();

        return $statement;
    }
}

if (!function_exists('db_fetch_one')) {
    /**
     * Fetch a single row from the database as an associative array.
     *
     * @return array<string,mixed>|null
     */
    function db_fetch_one(string $sql, array $params = []): ?array
    {
        $statement = db_query($sql, $params);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }
}

if (!function_exists('db_fetch_all')) {
    /**
     * Fetch all rows from the database as an array of associative arrays.
     *
     * @return list<array<string,mixed>>
     */
    function db_fetch_all(string $sql, array $params = []): array
    {
        $statement = db_query($sql, $params);
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return $rows !== false ? $rows : [];
    }
}

if (!function_exists('db_execute')) {
    /**
     * Execute a statement without caring about the result set.
     *
     * @param array<string,int|float|string|null> $params
     */
    function db_execute(string $sql, array $params = []): int
    {
        $statement = db_query($sql, $params);
        return $statement->rowCount();
    }
}

if (!function_exists('db_last_insert_id')) {
    function db_last_insert_id(): int
    {
        return (int)db()->lastInsertId();
    }
}

if (!function_exists('db_transaction')) {
    /**
     * Execute a callback within a transaction, rolling back on exception.
     */
    function db_transaction(callable $callback): mixed
    {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();

            return $result;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }
}
