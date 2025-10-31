<?php
declare(strict_types=1);

namespace Cms\Models\Repositories;

use Cms\Models\Category;

final class CategoryRepository extends BaseRepository
{
    protected function table(): string
    {
        return Category::TABLE;
    }

    protected function modelClass(): string
    {
        return Category::class;
    }

    /**
     * @return list<Category>
     */
    public function children(?int $parentId = null): array
    {
        if ($parentId === null) {
            $sql = 'SELECT * FROM `' . Category::TABLE . '` WHERE `parent_id` IS NULL ORDER BY `name`';
            $rows = db_fetch_all($sql);
        } else {
            $sql = 'SELECT * FROM `' . Category::TABLE . '` WHERE `parent_id` = :parent ORDER BY `name`';
            $rows = db_fetch_all($sql, ['parent' => $parentId]);
        }

        return array_map(fn(array $row) => $this->map($row), $rows);
    }
}
