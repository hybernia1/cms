<?php
declare(strict_types=1);

namespace Cms\Models\Repositories;

use Cms\Models\ProductAttribute;
use Cms\Models\ProductVariant;

final class ProductVariantRepository extends BaseRepository
{
    protected function table(): string
    {
        return ProductVariant::TABLE;
    }

    protected function modelClass(): string
    {
        return ProductVariant::class;
    }

    /**
     * @return list<ProductVariant>
     */
    public function forProduct(int $productId): array
    {
        $sql = 'SELECT * FROM `' . ProductVariant::TABLE . '` WHERE `product_id` = :product ORDER BY `sort_order`, `id`';
        $rows = db_fetch_all($sql, ['product' => $productId]);

        return array_map(fn(array $row) => $this->map($row), $rows);
    }

    /**
     * @return list<array{attribute:ProductAttribute,value:string|null}>
     */
    public function attributes(int $variantId): array
    {
        $sql = 'SELECT pa.*, pva.`value` AS `pivot_value`'
            . ' FROM `' . ProductAttribute::TABLE . '` pa'
            . ' INNER JOIN `product_variant_attributes` pva ON pva.`attribute_id` = pa.`id`'
            . ' WHERE pva.`variant_id` = :variant'
            . ' ORDER BY pa.`name`';

        $rows = db_fetch_all($sql, ['variant' => $variantId]);

        return array_map(
            static fn(array $row) => [
                'attribute' => new ProductAttribute($row),
                'value'     => $row['pivot_value'] ?? null,
            ],
            $rows
        );
    }

    /**
     * @param array<int|string,string|null> $attributeValues keyed by attribute ID
     */
    public function syncAttributes(int $variantId, array $attributeValues): void
    {
        db_transaction(function () use ($variantId, $attributeValues): void {
            db_execute('DELETE FROM `product_variant_attributes` WHERE `variant_id` = :variant', ['variant' => $variantId]);

            if ($attributeValues === []) {
                return;
            }

            $statement = db()->prepare(
                'INSERT INTO `product_variant_attributes` (`variant_id`, `attribute_id`, `value`)'
                . ' VALUES (:variant, :attribute, :value)'
            );
            if ($statement === false) {
                throw new \RuntimeException('Unable to prepare variant attribute assignment.');
            }

            foreach ($attributeValues as $attributeId => $value) {
                $statement->execute([
                    'variant'   => $variantId,
                    'attribute' => (int)$attributeId,
                    'value'     => $value,
                ]);
            }
        });
    }
}
