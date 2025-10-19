<?php
declare(strict_types=1);

namespace Cms\Admin\Domain\Repositories;

use Core\Database\Init as DB;

final class TermsRepository
{
    public function find(int $id): ?array
    {
        return DB::query()->table('terms')->select(['*'])->where('id','=',$id)->first();
    }

    public function findBySlug(string $slug): ?array
    {
        return DB::query()->table('terms')->select(['*'])->where('slug','=',$slug)->first();
    }

    public function findByNameAndType(string $name, string $type): ?array
    {
        return DB::query()->table('terms')
            ->select(['*'])
            ->where('name', '=', $name)
            ->where('type', '=', $type)
            ->first();
    }

    public function create(array $data): int
    {
        return (int) DB::query()->table('terms')->insert($data)->insertGetId();
    }

    public function attachToPost(int $postId, int $termId): void
    {
        DB::query()->table('post_terms')->insert(['post_id'=>$postId,'term_id'=>$termId])->execute();
    }

    public function detachFromPost(int $postId, int $termId): int
    {
        return DB::query()->table('post_terms')->delete()
            ->where('post_id','=',$postId)->where('term_id','=',$termId)->execute();
    }

    public function forPost(int $postId): array
    {
        return DB::query()->table('post_terms','pt')
            ->select(['t.id','t.name','t.slug','t.type'])
            ->join('terms t', 'pt.term_id','=','t.id')
            ->where('pt.post_id','=',$postId)
            ->get();
    }
}
