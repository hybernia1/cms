<?php
declare(strict_types=1);

namespace Cms\Models\Repositories;

use Cms\Models\Address;

final class AddressRepository extends BaseRepository
{
    protected function table(): string
    {
        return Address::TABLE;
    }

    protected function modelClass(): string
    {
        return Address::class;
    }

    /**
     * @return list<Address>
     */
    public function forOrder(int $orderId): array
    {
        $sql = 'SELECT * FROM `' . Address::TABLE . '` WHERE `order_id` = :order ORDER BY `type`';
        $rows = db_fetch_all($sql, ['order' => $orderId]);

        return array_map(fn(array $row) => $this->map($row), $rows);
    }

    public function findByOrderAndType(int $orderId, string $type): ?Address
    {
        $sql = 'SELECT * FROM `' . Address::TABLE . '` WHERE `order_id` = :order AND `type` = :type LIMIT 1';
        $row = db_fetch_one($sql, ['order' => $orderId, 'type' => $type]);

        return $row ? new Address($row) : null;
    }
}
