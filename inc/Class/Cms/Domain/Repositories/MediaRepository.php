<?php
declare(strict_types=1);

namespace Cms\Domain\Repositories;

use Core\Database\Init as DB;

final class MediaRepository
{
    public function find(int $id): ?array
    {
        return DB::query()->table('media')->select(['*'])->where('id','=',$id)->first();
    }

    public function create(array $data): int
    {
        return (int) DB::query()->table('media')->insert($data)->insertGetId();
    }

    public function delete(int $id): int
    {
        return DB::query()->table('media')->delete()->where('id','=',$id)->execute();
    }

    public function attachToPost(int $postId, int $mediaId, string $role = 'attachment'): void
    {
        DB::query()->table('post_media')->insert([
            'post_id' => $postId,
            'media_id'=> $mediaId,
            'role'    => $role,
        ])->execute();
    }

    public function detachFromPost(int $postId, int $mediaId): int
    {
        return DB::query()->table('post_media')->delete()
            ->where('post_id','=',$postId)->where('media_id','=',$mediaId)->execute();
    }
}
