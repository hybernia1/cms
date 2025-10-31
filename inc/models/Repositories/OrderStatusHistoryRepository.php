<?php
declare(strict_types=1);

namespace Cms\Models\Repositories;

use Cms\Models\OrderStatusHistory;

final class OrderStatusHistoryRepository extends BaseRepository
{
    protected function table(): string
    {
        return OrderStatusHistory::TABLE;
    }

    protected function modelClass(): string
    {
        return OrderStatusHistory::class;
    }

    /**
     * @return list<OrderStatusHistory>
     */
    public function forOrder(int $orderId): array
    {
        $sql = 'SELECT * FROM `' . OrderStatusHistory::TABLE . '` WHERE `order_id` = :order ORDER BY `id`';
        $rows = db_fetch_all($sql, ['order' => $orderId]);

        return array_map(fn(array $row) => $this->map($row), $rows);
    }
}
