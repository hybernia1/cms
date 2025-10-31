<?php
declare(strict_types=1);

namespace Cms\Models\Repositories;

use Cms\Models\BaseModel;
use PDO;
use RuntimeException;

abstract class BaseRepository
{
    protected PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? db();
    }

    abstract protected function table(): string;

    /**
     * @return class-string<BaseModel>
     */
    abstract protected function modelClass(): string;

    /**
     * @return list<BaseModel>
     */
    public function all(): array
    {
        $sql = sprintf('SELECT * FROM `%s` ORDER BY `id`', $this->table());
        $rows = db_fetch_all($sql);

        return array_map(fn(array $row) => $this->map($row), $rows);
    }

    public function find(int $id): ?BaseModel
    {
        $sql = sprintf('SELECT * FROM `%s` WHERE `id` = :id', $this->table());
        $row = db_fetch_one($sql, ['id' => $id]);

        return $row ? $this->map($row) : null;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): BaseModel
    {
        if ($data === []) {
            throw new RuntimeException('Cannot create empty record.');
        }

        [$sql, $params] = $this->buildInsert($data);
        db_execute($sql, $params);

        $id = db_last_insert_id();
        $created = $this->find($id);
        if ($created === null) {
            $data['id'] = $id;
            $created = $this->map($data);
        }

        return $created;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): BaseModel
    {
        if ($data === []) {
            $record = $this->find($id);
            if ($record === null) {
                throw new RuntimeException('Record not found.');
            }

            return $record;
        }

        [$sql, $params] = $this->buildUpdate($id, $data);
        db_execute($sql, $params);

        $record = $this->find($id);
        if ($record === null) {
            throw new RuntimeException('Record not found after update.');
        }

        return $record;
    }

    public function delete(int $id): void
    {
        $sql = sprintf('DELETE FROM `%s` WHERE `id` = :id', $this->table());
        db_execute($sql, ['id' => $id]);
    }

    /**
     * @param array<string,mixed> $row
     */
    protected function map(array $row): BaseModel
    {
        $class = $this->modelClass();

        return new $class($row);
    }

    /**
     * @param array<string,mixed> $data
     * @return array{0:string,1:array<string,mixed>}
     */
    private function buildInsert(array $data): array
    {
        $columns = array_keys($data);
        $quotedColumns = array_map(fn(string $column) => sprintf('`%s`', $column), $columns);
        $placeholders = array_map(fn(string $column) => sprintf(':%s', $column), $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $this->table(),
            implode(', ', $quotedColumns),
            implode(', ', $placeholders)
        );

        return [$sql, $data];
    }

    /**
     * @param array<string,mixed> $data
     * @return array{0:string,1:array<string,mixed>}
     */
    private function buildUpdate(int $id, array $data): array
    {
        $columns = array_keys($data);
        $assignments = array_map(fn(string $column) => sprintf('`%s` = :%s', $column, $column), $columns);

        $data['id'] = $id;
        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE `id` = :id',
            $this->table(),
            implode(', ', $assignments)
        );

        return [$sql, $data];
    }
}
