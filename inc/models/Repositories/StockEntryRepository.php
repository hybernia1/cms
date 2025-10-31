<?php
declare(strict_types=1);

namespace Cms\Models\Repositories;

use Cms\Models\StockEntry;

final class StockEntryRepository extends BaseRepository
{
    protected function table(): string
    {
        return StockEntry::TABLE;
    }

    protected function modelClass(): string
    {
        return StockEntry::class;
    }

    /**
     * @return list<StockEntry>
     */
    public function forVariant(int $variantId): array
    {
        $sql = 'SELECT * FROM `' . StockEntry::TABLE . '` WHERE `variant_id` = :variant ORDER BY `created_at` DESC, `id` DESC';
        $rows = db_fetch_all($sql, ['variant' => $variantId]);

        return array_map(fn(array $row) => $this->map($row), $rows);
    }
}
