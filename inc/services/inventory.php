<?php
declare(strict_types=1);

namespace Cms\Services;

use Cms\Models\Repositories\ProductVariantRepository;
use Cms\Models\Repositories\StockEntryRepository;
use Cms\Models\StockEntry;

final class InventoryService
{
    private ProductVariantRepository $variants;
    private StockEntryRepository $entries;

    public function __construct(?ProductVariantRepository $variants = null, ?StockEntryRepository $entries = null)
    {
        $this->variants = $variants ?? new ProductVariantRepository();
        $this->entries = $entries ?? new StockEntryRepository();
    }

    /**
     * @param array<string,mixed> $meta
     */
    public function adjust(int $variantId, int $quantityChange, string $reason, ?string $reference = null, array $meta = []): StockEntry
    {
        return db_transaction(function () use ($variantId, $quantityChange, $reason, $reference, $meta): StockEntry {
            $variant = $this->variants->find($variantId);
            if ($variant === null) {
                throw new \InvalidArgumentException('Variant not found.');
            }

            $currentQuantity = isset($variant->inventory_quantity) ? (int)$variant->inventory_quantity : 0;
            $newQuantity = $currentQuantity + $quantityChange;

            $this->variants->update((int)$variant->id, [
                'inventory_quantity' => $newQuantity,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);

            $payload = [
                'variant_id' => $variantId,
                'quantity_change' => $quantityChange,
                'reason' => $reason !== '' ? $reason : null,
                'reference' => $reference !== '' ? $reference : null,
                'meta' => $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ];

            /** @var StockEntry $entry */
            $entry = $this->entries->create($payload);

            return $entry;
        });
    }

    /**
     * @return list<StockEntry>
     */
    public function historyForVariant(int $variantId): array
    {
        return $this->entries->forVariant($variantId);
    }
}

