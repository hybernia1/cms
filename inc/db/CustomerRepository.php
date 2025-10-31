<?php
declare(strict_types=1);

namespace Cms\Db;

final class CustomerRepository extends BaseRepository
{
    public function find(int $id): ?array
    {
        return $this->table('customers')->select(['*'])->where('id', '=', $id)->first();
    }

    public function findByEmail(string $email): ?array
    {
        return $this->table('customers')->select(['*'])->where('email', '=', $email)->first();
    }

    public function create(array $data): int
    {
        return (int) $this->table('customers')->insert($data)->insertGetId();
    }

    public function update(int $id, array $data): int
    {
        return $this->table('customers')->update($data)->where('id', '=', $id)->execute();
    }

    public function addresses(int $customerId): array
    {
        return $this->table('addresses')
            ->select(['*'])
            ->where('customer_id', '=', $customerId)
            ->get();
    }

    public function saveAddress(array $data): int
    {
        return (int) $this->table('addresses')->insert($data)->insertGetId();
    }
}
