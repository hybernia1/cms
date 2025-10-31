<?php
declare(strict_types=1);

namespace Cms\Models\Repositories;

use Cms\Models\OrderItem;

final class OrderItemRepository extends BaseRepository
{
    protected function table(): string
    {
        return OrderItem::TABLE;
    }

    protected function modelClass(): string
    {
        return OrderItem::class;
    }

    /**
     * @return list<OrderItem>
     */
    public function forOrder(int $orderId): array
    {
        $sql = 'SELECT * FROM `' . OrderItem::TABLE . '` WHERE `order_id` = :order ORDER BY `id`';
        $rows = db_fetch_all($sql, ['order' => $orderId]);

        return array_map(fn(array $row) => $this->map($row), $rows);
    }
}
