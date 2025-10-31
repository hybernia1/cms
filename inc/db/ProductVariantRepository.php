<?php
declare(strict_types=1);

namespace Cms\Db;

final class ProductVariantRepository extends BaseRepository
{
    public function listByProduct(int $productId): array
    {
        return $this->table('product_variants')
            ->select(['*'])
            ->where('product_id', '=', $productId)
            ->whereNull('deleted_at')
            ->orderBy('position', 'ASC')
            ->get();
    }

    public function find(int $id): ?array
    {
        return $this->table('product_variants')->select(['*'])->where('id', '=', $id)->first();
    }

    public function create(array $data): int
    {
        return (int) $this->table('product_variants')->insert($data)->insertGetId();
    }

    public function update(int $id, array $data): int
    {
        return $this->table('product_variants')->update($data)->where('id', '=', $id)->execute();
    }

    public function softDelete(int $id): int
    {
        return $this->markDeleted('product_variants', $id);
    }
}
