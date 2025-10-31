<?php
declare(strict_types=1);

namespace Cms\Db;

final class InvoiceRepository extends BaseRepository
{
    public function findByOrder(int $orderId): ?array
    {
        return $this->table('invoices')->select(['*'])->where('order_id', '=', $orderId)->first();
    }

    public function create(array $data): int
    {
        return (int) $this->table('invoices')->insert($data)->insertGetId();
    }

    public function update(int $id, array $data): int
    {
        return $this->table('invoices')->update($data)->where('id', '=', $id)->execute();
    }
}
