<?php
declare(strict_types=1);

namespace Cms\Db;

final class ProductAttributeRepository extends BaseRepository
{
    public function listForProduct(int $productId): array
    {
        return $this->table('product_attributes')
            ->select(['*'])
            ->where('product_id', '=', $productId)
            ->orderBy('position', 'ASC')
            ->get();
    }

    public function listForVariant(int $variantId): array
    {
        return $this->table('product_attributes')
            ->select(['*'])
            ->where('variant_id', '=', $variantId)
            ->orderBy('position', 'ASC')
            ->get();
    }

    public function create(array $data): int
    {
        return (int) $this->table('product_attributes')->insert($data)->insertGetId();
    }

    public function deleteForProduct(int $productId): int
    {
        return $this->table('product_attributes')->delete()->where('product_id', '=', $productId)->execute();
    }

    public function deleteForVariant(int $variantId): int
    {
        return $this->table('product_attributes')->delete()->where('variant_id', '=', $variantId)->execute();
    }
}
