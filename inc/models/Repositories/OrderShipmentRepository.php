<?php
declare(strict_types=1);

namespace Cms\Models\Repositories;

use Cms\Models\OrderShipment;

final class OrderShipmentRepository extends BaseRepository
{
    protected function table(): string
    {
        return OrderShipment::TABLE;
    }

    protected function modelClass(): string
    {
        return OrderShipment::class;
    }

    /**
     * @return list<OrderShipment>
     */
    public function forOrder(int $orderId): array
    {
        $sql = 'SELECT * FROM `' . OrderShipment::TABLE . '` WHERE `order_id` = :order ORDER BY `id`';
        $rows = db_fetch_all($sql, ['order' => $orderId]);

        return array_map(fn(array $row) => $this->map($row), $rows);
    }
}
