<?php
declare(strict_types=1);

namespace Cms\Models\Repositories;

use Cms\Models\StockReservation;

final class StockReservationRepository extends BaseRepository
{
    protected function table(): string
    {
        return StockReservation::TABLE;
    }

    protected function modelClass(): string
    {
        return StockReservation::class;
    }

    /**
     * @return list<StockReservation>
     */
    public function forOrder(int $orderId): array
    {
        $sql = 'SELECT * FROM `' . StockReservation::TABLE . '` WHERE `order_id` = :order ORDER BY `id`';
        $rows = db_fetch_all($sql, ['order' => $orderId]);

        return array_map(fn(array $row) => $this->map($row), $rows);
    }

    /**
     * @return list<StockReservation>
     */
    public function activeForOrder(int $orderId): array
    {
        $sql = 'SELECT * FROM `' . StockReservation::TABLE . '`'
            . ' WHERE `order_id` = :order AND `state` = :state ORDER BY `id`';
        $rows = db_fetch_all($sql, [
            'order' => $orderId,
            'state' => StockReservation::STATE_RESERVED,
        ]);

        return array_map(fn(array $row) => $this->map($row), $rows);
    }

    public function updateState(int $reservationId, string $state, ?string $note = null): StockReservation
    {
        $payload = [
            'state' => $state,
            'note' => $note,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];

        return $this->update($reservationId, $payload);
    }

    public function createReservation(int $orderId, int $variantId, int $quantity, string $state, ?string $reference = null, ?string $note = null): StockReservation
    {
        $payload = [
            'order_id' => $orderId,
            'variant_id' => $variantId,
            'quantity' => $quantity,
            'state' => $state,
            'reference' => $reference,
            'note' => $note,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];

        /** @var StockReservation $reservation */
        $reservation = $this->create($payload);

        return $reservation;
    }
}
