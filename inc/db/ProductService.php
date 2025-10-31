<?php
declare(strict_types=1);

namespace Cms\Db;

final class ProductService
{
    public function __construct(
        private readonly ProductRepository $products = new ProductRepository(),
        private readonly ProductVariantRepository $variants = new ProductVariantRepository(),
        private readonly ProductAttributeRepository $attributes = new ProductAttributeRepository()
    ) {
    }

    public function createProduct(array $product, array $variants = [], array $attributes = []): int
    {
        $productId = $this->products->create($product);

        foreach ($variants as $variant) {
            $variantData = $variant;
            $variantAttributes = [];

            if (!empty($variantData['attributes']) && is_array($variantData['attributes'])) {
                $variantAttributes = $variantData['attributes'];
            }

            unset($variantData['attributes']);
            $variantData['product_id'] = $productId;

            $variantId = $this->variants->create($variantData);

            foreach ($variantAttributes as $attr) {
                if (is_array($attr)) {
                    $attr['variant_id'] = $variantId;
                    $attr['product_id'] = $productId;
                    $this->attributes->create($attr);
                }
            }
        }

        foreach ($attributes as $attr) {
            $attr['product_id'] = $productId;
            $this->attributes->create($attr);
        }

        return $productId;
    }

    public function updateProduct(int $id, array $product): int
    {
        return $this->products->update($id, $product);
    }

    public function archiveProduct(int $id): int
    {
        return $this->products->softDelete($id);
    }

    public function variantsForProduct(int $productId): array
    {
        return $this->variants->listByProduct($productId);
    }
}
