<?php
declare(strict_types=1);

namespace Cms\Services;

use Cms\Models\Repositories\ProductVariantRepository;
use Cms\Models\Repositories\StockEntryRepository;
use Cms\Models\Repositories\StockReservationRepository;
use Cms\Models\StockEntry;
use Cms\Models\StockReservation;
use InvalidArgumentException;
use RuntimeException;
use function db;

final class InventoryService
{
    private ProductVariantRepository $variants;
    private StockEntryRepository $entries;
    private StockReservationRepository $reservations;

    public function __construct(
        ?ProductVariantRepository $variants = null,
        ?StockEntryRepository $entries = null,
        ?StockReservationRepository $reservations = null,
    ) {
        $this->variants = $variants ?? new ProductVariantRepository();
        $this->entries = $entries ?? new StockEntryRepository();
        $this->reservations = $reservations ?? new StockReservationRepository();
    }

    /**
     * @param array<string,mixed> $meta
     */
    public function adjust(int $variantId, int $quantityChange, string $reason, ?string $reference = null, array $meta = []): StockEntry
    {
        return $this->transaction(function () use ($variantId, $quantityChange, $reason, $reference, $meta): StockEntry {
            $snapshot = $this->variants->snapshotQuantities($variantId);
            if ($snapshot === null) {
                throw new InvalidArgumentException('Variant not found.');
            }

            $newQuantity = $snapshot['inventory_quantity'] + $quantityChange;
            if ($newQuantity < 0) {
                throw new RuntimeException('Resulting stock cannot be negative.');
            }

            $this->variants->incrementOnHand($variantId, $quantityChange);

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

    /**
     * @return array{on_hand:int,reserved:int,available:int,track_inventory:bool}
     */
    public function availability(int $variantId): array
    {
        $snapshot = $this->variants->snapshotQuantities($variantId);
        if ($snapshot === null) {
            throw new InvalidArgumentException('Variant not found.');
        }

        $onHand = $snapshot['inventory_quantity'];
        $reserved = max(0, $snapshot['inventory_reserved']);
        $available = max(0, $onHand - $reserved);

        return [
            'on_hand' => $onHand,
            'reserved' => $reserved,
            'available' => $available,
            'track_inventory' => $snapshot['track_inventory'],
        ];
    }

    /**
     * @param list<array<string,mixed>> $items
     */
    public function reserveForOrder(int $orderId, array $items, ?string $reference = null, ?string $note = null): void
    {
        $this->transaction(function () use ($orderId, $items, $reference, $note): void {
            if ($items === []) {
                return;
            }

            $active = $this->reservations->activeForOrder($orderId);
            if ($active !== []) {
                throw new RuntimeException('Order already has an active stock reservation.');
            }

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $variantId = isset($item['variant_id']) ? (int)$item['variant_id'] : 0;
                $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;
                if ($variantId <= 0 || $quantity <= 0) {
                    continue;
                }

                $snapshot = $this->variants->snapshotQuantities($variantId);
                if ($snapshot === null) {
                    continue;
                }
                if ($snapshot['track_inventory'] === false) {
                    continue;
                }

                $available = $snapshot['inventory_quantity'] - $snapshot['inventory_reserved'];
                if ($available < $quantity) {
                    throw new RuntimeException('Insufficient stock for variant ' . $variantId . '.');
                }

                $this->variants->incrementReserved($variantId, $quantity);
                $this->reservations->createReservation(
                    $orderId,
                    $variantId,
                    $quantity,
                    StockReservation::STATE_RESERVED,
                    $reference,
                    $note
                );
            }
        });
    }

    public function releaseForOrder(int $orderId, ?string $note = null): void
    {
        $this->transaction(function () use ($orderId, $note): void {
            $reservations = $this->reservations->activeForOrder($orderId);
            if ($reservations === []) {
                return;
            }

            foreach ($reservations as $reservation) {
                $variantId = isset($reservation->variant_id) ? (int)$reservation->variant_id : 0;
                $quantity = isset($reservation->quantity) ? (int)$reservation->quantity : 0;
                if ($variantId <= 0 || $quantity <= 0) {
                    continue;
                }

                $snapshot = $this->variants->snapshotQuantities($variantId);
                if ($snapshot === null) {
                    continue;
                }
                if ($snapshot['inventory_reserved'] < $quantity) {
                    throw new RuntimeException('Cannot release more stock than reserved for variant ' . $variantId . '.');
                }

                $this->variants->incrementReserved($variantId, -$quantity);
                $this->reservations->updateState((int)$reservation->id, StockReservation::STATE_RELEASED, $note);
            }
        });
    }

    public function consumeForOrder(int $orderId, ?string $reference = null, ?string $note = null): void
    {
        $this->transaction(function () use ($orderId, $reference, $note): void {
            $reservations = $this->reservations->activeForOrder($orderId);
            if ($reservations === []) {
                return;
            }

            foreach ($reservations as $reservation) {
                $variantId = isset($reservation->variant_id) ? (int)$reservation->variant_id : 0;
                $quantity = isset($reservation->quantity) ? (int)$reservation->quantity : 0;
                if ($variantId <= 0 || $quantity <= 0) {
                    continue;
                }

                $snapshot = $this->variants->snapshotQuantities($variantId);
                if ($snapshot === null) {
                    continue;
                }
                if ($snapshot['inventory_reserved'] < $quantity) {
                    throw new RuntimeException('Cannot consume more stock than reserved for variant ' . $variantId . '.');
                }
                if ($snapshot['inventory_quantity'] < $quantity) {
                    throw new RuntimeException('Insufficient on-hand inventory to fulfill variant ' . $variantId . '.');
                }

                $this->variants->incrementReserved($variantId, -$quantity);
                $this->variants->incrementOnHand($variantId, -$quantity);
                $this->reservations->updateState((int)$reservation->id, StockReservation::STATE_CONSUMED, $note);

                $meta = $note !== null ? ['note' => $note] : [];
                $this->entries->create([
                    'variant_id' => $variantId,
                    'quantity_change' => -$quantity,
                    'reason' => 'Order shipment',
                    'reference' => $reference,
                    'meta' => $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    'created_at' => gmdate('Y-m-d H:i:s'),
                ]);
            }
        });
    }

    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    private function transaction(callable $callback): mixed
    {
        $pdo = db();
        $manage = !$pdo->inTransaction();

        if ($manage) {
            $pdo->beginTransaction();
        }

        try {
            $result = $callback();
            if ($manage) {
                $pdo->commit();
            }

            return $result;
        } catch (\Throwable $exception) {
            if ($manage && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }
}
