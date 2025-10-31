<?php
declare(strict_types=1);

namespace Cms\Models\Repositories;

use Cms\Models\Customer;

final class CustomerRepository extends BaseRepository
{
    protected function table(): string
    {
        return Customer::TABLE;
    }

    protected function modelClass(): string
    {
        return Customer::class;
    }

    public function findByEmail(string $email): ?Customer
    {
        $normalized = trim($email);
        if ($normalized === '') {
            return null;
        }

        $sql = 'SELECT * FROM `' . Customer::TABLE . '` WHERE `email` = :email LIMIT 1';
        $row = db_fetch_one($sql, ['email' => $normalized]);

        return $row ? new Customer($row) : null;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function upsertByEmail(string $email, array $data): Customer
    {
        $existing = $this->findByEmail($email);
        if ($existing !== null && isset($existing->id)) {
            return $this->update((int)$existing->id, $data);
        }

        return $this->create($data);
    }
}
