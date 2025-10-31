<?php
declare(strict_types=1);

namespace Cms\Db;

final class OrderRepository extends BaseRepository
{
    public function find(int $id): ?array
    {
        return $this->table('orders')->select(['*'])->where('id', '=', $id)->first();
    }

    public function findByNumber(string $number): ?array
    {
        return $this->table('orders')->select(['*'])->where('order_number', '=', $number)->first();
    }

    public function create(array $data): int
    {
        return (int) $this->table('orders')->insert($data)->insertGetId();
    }

    public function update(int $id, array $data): int
    {
        return $this->table('orders')->update($data)->where('id', '=', $id)->execute();
    }

    public function items(int $orderId): array
    {
        return $this->table('order_items')
            ->select(['*'])
            ->where('order_id', '=', $orderId)
            ->get();
    }

    public function addItem(array $data): int
    {
        return (int) $this->table('order_items')->insert($data)->insertGetId();
    }

    public function statuses(): array
    {
        return $this->table('order_statuses')
            ->select(['*'])
            ->orderBy('sort_order', 'ASC')
            ->get();
    }
}
