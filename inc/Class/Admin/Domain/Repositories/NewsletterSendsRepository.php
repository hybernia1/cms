<?php
declare(strict_types=1);

namespace Cms\Admin\Domain\Repositories;

use Core\Database\Init as DB;

final class NewsletterSendsRepository
{
    public function create(array $data): int
    {
        return (int) DB::query()->table('newsletter_sends')->insert($data)->insertGetId();
    }

    public function update(int $id, array $data): int
    {
        return DB::query()
            ->table('newsletter_sends')
            ->update($data)
            ->where('id', '=', $id)
            ->execute();
    }

    public function delete(int $id): int
    {
        return DB::query()
            ->table('newsletter_sends')
            ->delete()
            ->where('id', '=', $id)
            ->execute();
    }

    public function find(int $id): ?array
    {
        return DB::query()
            ->table('newsletter_sends', 'c')
            ->select([
                'c.*',
                'u.name AS author_name',
                'u.email AS author_email',
            ])
            ->leftJoin('users u', 'u.id', '=', 'c.created_by')
            ->where('c.id', '=', $id)
            ->first();
    }

    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $query = DB::query()
            ->table('newsletter_sends', 'c')
            ->select([
                'c.*',
                'u.name AS author_name',
                'u.email AS author_email',
            ])
            ->leftJoin('users u', 'u.id', '=', 'c.created_by')
            ->orderBy('c.created_at', 'DESC');

        $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(static function ($where) use ($like): void {
                $where->whereLike('c.subject', $like)
                    ->orWhere('c.body', 'LIKE', $like);
            });
        }

        $author = isset($filters['author']) ? (int) $filters['author'] : 0;
        if ($author > 0) {
            $query->where('c.created_by', '=', $author);
        }

        return $query->paginate($page, $perPage);
    }

    public function duplicate(array $data): int
    {
        return (int) DB::query()
            ->table('newsletter_sends')
            ->insert($data)
            ->insertGetId();
    }

    /**
     * @return array<int,array{id:int,name:string,email:string|null}>
     */
    public function authors(): array
    {
        $rows = DB::query()
            ->table('newsletter_sends', 'c')
            ->select([
                'c.created_by AS id',
                'u.name AS name',
                'u.email AS email',
            ])
            ->leftJoin('users u', 'u.id', '=', 'c.created_by')
            ->groupBy('c.created_by', 'u.name', 'u.email')
            ->orderBy('u.name', 'ASC')
            ->get();

        $authors = [];
        foreach ($rows as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id <= 0) {
                continue;
            }
            $authors[] = [
                'id'    => $id,
                'name'  => (string) ($row['name'] ?? ''),
                'email' => isset($row['email']) ? (string) $row['email'] : null,
            ];
        }

        return $authors;
    }

    public function latest(): ?array
    {
        return DB::query()
            ->table('newsletter_sends')
            ->select(['*'])
            ->orderBy('created_at', 'DESC')
            ->limit(1)
            ->first();
    }
}
