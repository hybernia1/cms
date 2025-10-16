<?php
declare(strict_types=1);

namespace Cms\Domain\Repositories;

use Core\Database\Init as DB;

final class CommentsRepository
{
    public function create(array $data): int
    {
        return (int) DB::query()->table('comments')->insert($data)->insertGetId();
    }

    public function update(int $id, array $data): int
    {
        return DB::query()->table('comments')->update($data)->where('id','=',$id)->execute();
    }

    public function forPost(int $postId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $q = DB::query()->table('comments','c')->select([
            'c.id','c.user_id','c.parent_id','c.author_name','c.author_email',
            'c.content','c.status','c.created_at'
        ])->where('c.post_id','=',$postId);

        if (!empty($filters['status'])) $q->where('c.status','=', (string)$filters['status']);
        if (isset($filters['parent']))  $q->where('c.parent_id','=', (int)$filters['parent']);

        $q->orderBy('c.created_at','ASC');
        return $q->paginate($page, $perPage);
    }
}
