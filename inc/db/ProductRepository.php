<?php
declare(strict_types=1);

namespace Cms\Db;

final class ProductRepository extends BaseRepository
{
    public function find(int $id): ?array
    {
        return $this->table('products')->select(['*'])->where('id', '=', $id)->first();
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->table('products')->select(['*'])->where('slug', '=', $slug)->first();
    }

    public function list(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $query = $this->table('products', 'p')->select([
            'p.id', 'p.name', 'p.slug', 'p.status', 'p.type', 'p.price', 'p.currency',
            'p.category_id', 'p.created_at', 'p.updated_at', 'p.deleted_at'
        ])->whereNull('p.deleted_at');

        if (!empty($filters['status'])) {
            $query->where('p.status', '=', (string) $filters['status']);
        }
        if (!empty($filters['category_id'])) {
            $query->where('p.category_id', '=', (int) $filters['category_id']);
        }
        if (!empty($filters['q'])) {
            $term = '%' . trim((string) $filters['q']) . '%';
            $query->whereLike('p.name', $term);
        }

        $query->orderBy('p.created_at', 'DESC');
        return $query->paginate($page, $perPage);
    }

    public function create(array $data): int
    {
        return (int) $this->table('products')->insert($data)->insertGetId();
    }

    public function update(int $id, array $data): int
    {
        return $this->table('products')->update($data)->where('id', '=', $id)->execute();
    }

    public function softDelete(int $id): int
    {
        return $this->markDeleted('products', $id);
    }
}
