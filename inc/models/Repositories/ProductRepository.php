<?php
declare(strict_types=1);

namespace Cms\Models\Repositories;

use Cms\Models\Category;
use Cms\Models\Product;
use Cms\Models\ProductVariant;

final class ProductRepository extends BaseRepository
{
    protected function table(): string
    {
        return Product::TABLE;
    }

    protected function modelClass(): string
    {
        return Product::class;
    }

    /**
     * @return list<Category>
     */
    public function categories(int $productId): array
    {
        $sql = 'SELECT c.* FROM `categories` c'
            . ' INNER JOIN `category_product` cp ON cp.`category_id` = c.`id`'
            . ' WHERE cp.`product_id` = :product'
            . ' ORDER BY c.`name`';

        $rows = db_fetch_all($sql, ['product' => $productId]);

        return array_map(static fn(array $row) => new Category($row), $rows);
    }

    /**
     * @param list<int> $categoryIds
     */
    public function syncCategories(int $productId, array $categoryIds): void
    {
        db_transaction(function () use ($productId, $categoryIds): void {
            db_execute('DELETE FROM `category_product` WHERE `product_id` = :product', ['product' => $productId]);

            if ($categoryIds === []) {
                return;
            }

            $statement = db()->prepare('INSERT INTO `category_product` (`product_id`, `category_id`) VALUES (:product, :category)');
            if ($statement === false) {
                throw new \RuntimeException('Unable to prepare category assignment statement.');
            }

            foreach (array_unique($categoryIds) as $categoryId) {
                $statement->execute([
                    'product'  => $productId,
                    'category' => (int)$categoryId,
                ]);
            }
        });
    }

    /**
     * @return list<ProductVariant>
     */
    public function variants(int $productId): array
    {
        return (new ProductVariantRepository($this->pdo))->forProduct($productId);
    }
}
