<?php
declare(strict_types=1);

namespace Cms\Front\Data;

use Cms\Admin\Utils\LinkGenerator;
use Cms\Models\Repositories\ProductRepository;
use Cms\Models\Repositories\ProductVariantRepository;
use Cms\Models\Category;
use Cms\Models\Product;
use Cms\Models\ProductVariant;
use Throwable;

final class ProductCatalog
{
    private ProductRepository $products;
    private ProductVariantRepository $variants;
    private LinkGenerator $links;

    public function __construct(?ProductRepository $products = null, ?ProductVariantRepository $variants = null, ?LinkGenerator $links = null)
    {
        $this->products = $products ?? new ProductRepository();
        $this->variants = $variants ?? new ProductVariantRepository();
        $this->links = $links ?? new LinkGenerator();
    }

    /**
     * @return array{items:list<array<string,mixed>>,pagination:array{page:int,per_page:int,total:int,pages:int}}
     */
    public function paginate(int $page = 1, int $perPage = 12): array
    {
        $page = max(1, $page);
        $limit = max(1, $perPage);
        $offset = ($page - 1) * $limit;

        $items = [];
        $total = 0;

        try {
            $countRow = db_fetch_one('SELECT COUNT(*) AS aggregate FROM `products` WHERE `status` = :status', ['status' => 'active']);
            $total = isset($countRow['aggregate']) ? (int)$countRow['aggregate'] : 0;

            $sql = 'SELECT * FROM `products` WHERE `status` = :status ORDER BY `created_at` DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
            $rows = db_fetch_all($sql, ['status' => 'active']);

            foreach ($rows as $row) {
                $items[] = $this->mapProduct($row);
            }
        } catch (Throwable $exception) {
            error_log('Failed to load product listing: ' . $exception->getMessage());
        }

        $pages = $limit > 0 ? (int)max(1, (int)ceil($total / $limit)) : 1;

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'pages' => $pages,
            ],
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        $normalized = trim($slug);
        if ($normalized === '') {
            return null;
        }

        try {
            $row = db_fetch_one(
                'SELECT * FROM `products` WHERE `slug` = :slug AND `status` = :status LIMIT 1',
                ['slug' => $normalized, 'status' => 'active']
            );
        } catch (Throwable $exception) {
            error_log('Failed to load product detail: ' . $exception->getMessage());
            return null;
        }

        if (!$row) {
            return null;
        }

        $product = $this->mapProduct($row);
        $product['description'] = (string)($row['description'] ?? '');
        $product['short_description'] = (string)($row['short_description'] ?? '');
        $product['categories'] = $this->mapCategories($this->products->categories((int)$row['id']));
        $product['variants'] = $this->mapVariants($this->products->variants((int)$row['id']));

        return $product;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function mapProduct(array $row): array
    {
        $slug = (string)($row['slug'] ?? '');
        $price = isset($row['price']) ? (float)$row['price'] : 0.0;
        $currency = isset($row['currency']) ? (string)$row['currency'] : 'USD';

        return [
            'id' => (int)($row['id'] ?? 0),
            'name' => (string)($row['name'] ?? ''),
            'slug' => $slug,
            'short_description' => (string)($row['short_description'] ?? ''),
            'price' => $price,
            'currency' => $currency,
            'status' => (string)($row['status'] ?? ''),
            'url' => $slug !== '' ? $this->links->productDetail($slug) : '',
        ];
    }

    /**
     * @param list<Category> $categories
     * @return list<array<string,mixed>>
     */
    private function mapCategories(array $categories): array
    {
        $mapped = [];
        foreach ($categories as $category) {
            $mapped[] = [
                'id' => isset($category->id) ? (int)$category->id : 0,
                'name' => (string)($category->name ?? ''),
                'slug' => (string)($category->slug ?? ''),
            ];
        }

        return $mapped;
    }

    /**
     * @param list<ProductVariant> $variants
     * @return list<array<string,mixed>>
     */
    private function mapVariants(array $variants): array
    {
        $mapped = [];
        foreach ($variants as $variant) {
            $attributes = [];
            try {
                $attributePairs = $this->variants->attributes((int)$variant->id);
                foreach ($attributePairs as $pair) {
                    $attribute = $pair['attribute'];
                    $attributes[] = [
                        'name' => (string)($attribute->name ?? ''),
                        'value' => isset($pair['value']) ? (string)$pair['value'] : null,
                    ];
                }
            } catch (Throwable $exception) {
                error_log('Failed to load variant attributes: ' . $exception->getMessage());
            }

            $mapped[] = [
                'id' => isset($variant->id) ? (int)$variant->id : 0,
                'product_id' => isset($variant->product_id) ? (int)$variant->product_id : 0,
                'name' => (string)($variant->name ?? ''),
                'sku' => (string)($variant->sku ?? ''),
                'price' => isset($variant->price) ? (float)$variant->price : 0.0,
                'currency' => (string)($variant->currency ?? ''),
                'on_hand' => isset($variant->inventory_quantity) ? (int)$variant->inventory_quantity : 0,
                'reserved' => isset($variant->inventory_reserved) ? (int)$variant->inventory_reserved : 0,
                'stock' => max(
                    0,
                    (isset($variant->inventory_quantity) ? (int)$variant->inventory_quantity : 0)
                    - (isset($variant->inventory_reserved) ? (int)$variant->inventory_reserved : 0)
                ),
                'attributes' => $attributes,
            ];
        }

        return $mapped;
    }
}
