<?php
declare(strict_types=1);

namespace Cms\Models\Repositories;

use Cms\Models\Address;
use Cms\Models\Order;
use Cms\Models\OrderItem;

final class OrderRepository extends BaseRepository
{
    protected function table(): string
    {
        return Order::TABLE;
    }

    protected function modelClass(): string
    {
        return Order::class;
    }

    public function findByNumber(string $orderNumber): ?Order
    {
        $sql = 'SELECT * FROM `' . Order::TABLE . '` WHERE `order_number` = :number LIMIT 1';
        $row = db_fetch_one($sql, ['number' => $orderNumber]);

        return $row ? new Order($row) : null;
    }

    /**
     * @return list<OrderItem>
     */
    public function items(int $orderId): array
    {
        return (new OrderItemRepository($this->pdo))->forOrder($orderId);
    }

    /**
     * @return list<Address>
     */
    public function addresses(int $orderId): array
    {
        return (new AddressRepository($this->pdo))->forOrder($orderId);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function addItem(int $orderId, array $data): OrderItem
    {
        $data['order_id'] = $orderId;
        return (new OrderItemRepository($this->pdo))->create($data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function setAddress(int $orderId, string $type, array $data): Address
    {
        $repository = new AddressRepository($this->pdo);
        $existing = $repository->findByOrderAndType($orderId, $type);

        $payload = $data + [
            'order_id' => $orderId,
            'type'     => $type,
        ];

        if ($existing === null) {
            return $repository->create($payload);
        }

        return $repository->update((int)$existing->id, $payload);
    }
}
