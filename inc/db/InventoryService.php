<?php
declare(strict_types=1);

namespace Cms\Db;

final class InventoryService
{
    public function __construct(private readonly InventoryRepository $inventory = new InventoryRepository())
    {
    }

    public function registerMovement(array $data): int
    {
        $data['occurred_at'] = $data['occurred_at'] ?? date('Y-m-d H:i:s');
        return $this->inventory->recordMovement($data);
    }

    public function locations(): array
    {
        return $this->inventory->locations();
    }

    public function createLocation(array $data): int
    {
        return $this->inventory->createLocation($data);
    }
}
