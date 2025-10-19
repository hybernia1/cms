<?php
declare(strict_types=1);

namespace Cms\Admin\Domain\Repositories;

use Core\Database\Init as DB;

final class UsersRepository
{
    public function find(int $id): ?array
    {
        return DB::query()->table('users')->select(['*'])->where('id','=',$id)->first();
    }

    public function findByEmail(string $email): ?array
    {
        return DB::query()->table('users')->select(['*'])->where('email','=',$email)->first();
    }

    public function create(array $data): int
    {
        return (int) DB::query()->table('users')->insert($data)->insertGetId();
    }

    public function update(int $id, array $data): int
    {
        return DB::query()->table('users')->update($data)->where('id','=',$id)->execute();
    }

    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $q = DB::query()->table('users','u')->select(['u.id','u.name','u.email','u.active','u.role','u.created_at']);
        if (isset($filters['active'])) $q->where('u.active','=', (int)$filters['active']);
        if (!empty($filters['role'])) $q->where('u.role','=', (string)$filters['role']);
        if (!empty($filters['q'])) {
            $term = '%' . trim((string)$filters['q']) . '%';
            $q->where(function($w) use ($term) {
                $w->whereLike('u.name',$term)->orWhere('u.email','LIKE',$term);
            });
        }
        $q->orderBy('u.id','DESC');
        return $q->paginate($page, $perPage);
    }
}
