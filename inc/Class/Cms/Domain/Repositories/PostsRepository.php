<?php
declare(strict_types=1);

namespace Cms\Domain\Repositories;

use Core\Database\Init as DB;

final class PostsRepository
{
    public function find(int $id): ?array
    {
        return DB::query()->table('posts')->select(['*'])->where('id','=',$id)->first();
    }

    public function findBySlug(string $slug, string $type): ?array
    {
        return DB::query()->table('posts')->select(['*'])
            ->where('slug','=',$slug)->where('type','=',$type)->first();
    }

    public function create(array $data): int
    {
        return (int) DB::query()->table('posts')->insert($data)->insertGetId();
    }

    public function update(int $id, array $data): int
    {
        return DB::query()->table('posts')->update($data)->where('id','=',$id)->execute();
    }

    public function delete(int $id): int
    {
        return DB::query()->table('posts')->delete()->where('id','=',$id)->execute();
    }

    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $q = DB::query()->table('posts','p')->select([
            'p.id','p.title','p.slug','p.type','p.status','p.author_id','p.thumbnail_id',
            'p.comments_allowed','p.published_at','p.created_at'
        ]);

        if (!empty($filters['type']))   $q->where('p.type','=', (string)$filters['type']);
        if (!empty($filters['status'])) $q->where('p.status','=', (string)$filters['status']);
        if (!empty($filters['author'])) $q->where('p.author_id','=', (int)$filters['author']);
        if (!empty($filters['q'])) {
            $term = '%' . trim((string)$filters['q']) . '%';
            $q->whereLike('p.title', $term);
        }

        $q->orderBy('p.created_at','DESC');
        return $q->paginate($page, $perPage);
    }

    /**
     * @return array<string,int>
     */
    public function countByStatus(string $type): array
    {
        $rows = DB::query()->table('posts')
            ->select(['status', 'COUNT(*) AS aggregate'])
            ->where('type', '=', $type)
            ->groupBy('status')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $status = (string)($row['status'] ?? '');
            $count = isset($row['aggregate']) ? (int)$row['aggregate'] : 0;
            $result[$status] = $count;
        }

        $result['__total'] = array_sum($result);

        return $result;
    }

    /**
     * @return array<int,array{id:int,title:string,type:string,created_at:?string}>
     */
    public function latestDrafts(string $type, int $limit): array
    {
        return DB::query()->table('posts')
            ->select(['id','title','type','created_at'])
            ->where('type', '=', $type)
            ->where('status', '=', 'draft')
            ->orderBy('created_at', 'DESC')
            ->limit(max(1, $limit))
            ->get();
    }
}
