<?php
declare(strict_types=1);

namespace Cms\Db;

use Core\Database\Init as DB;
use Core\Database\Query;

abstract class BaseRepository
{
    protected function table(string $table, ?string $alias = null): Query
    {
        return DB::query()->table($table, $alias);
    }

    /**
     * Soft delete helper – expects a deleted_at column.
     */
    protected function markDeleted(string $table, int $id): int
    {
        return DB::query()
            ->table($table)
            ->update(['deleted_at' => date('Y-m-d H:i:s')])
            ->where('id', '=', $id)
            ->execute();
    }
}
