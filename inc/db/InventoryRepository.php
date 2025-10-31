<?php
declare(strict_types=1);

namespace Cms\Db;

final class InventoryRepository extends BaseRepository
{
    public function locations(): array
    {
        return $this->table('inventory_locations')
            ->select(['*'])
            ->whereNull('deleted_at')
            ->orderBy('name', 'ASC')
            ->get();
    }

    public function createLocation(array $data): int
    {
        return (int) $this->table('inventory_locations')->insert($data)->insertGetId();
    }

    public function recordMovement(array $data): int
    {
        return (int) $this->table('inventory_movements')->insert($data)->insertGetId();
    }

    public function latestMovements(int $variantId, int $limit = 20): array
    {
        return $this->table('inventory_movements')
            ->select(['*'])
            ->where('variant_id', '=', $variantId)
            ->orderBy('occurred_at', 'DESC')
            ->limit(max(1, $limit))
            ->get();
    }
}
