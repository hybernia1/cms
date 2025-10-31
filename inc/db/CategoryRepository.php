<?php
declare(strict_types=1);

namespace Cms\Db;

final class CategoryRepository extends BaseRepository
{
    public function tree(): array
    {
        return $this->table('categories', 'c')
            ->select(['c.*'])
            ->whereNull('c.deleted_at')
            ->orderBy('c.parent_id', 'ASC')
            ->orderBy('c.name', 'ASC')
            ->get();
    }

    public function find(int $id): ?array
    {
        return $this->table('categories')->select(['*'])->where('id', '=', $id)->first();
    }

    public function create(array $data): int
    {
        return (int) $this->table('categories')->insert($data)->insertGetId();
    }

    public function update(int $id, array $data): int
    {
        return $this->table('categories')->update($data)->where('id', '=', $id)->execute();
    }

    public function softDelete(int $id): int
    {
        return $this->markDeleted('categories', $id);
    }
}
