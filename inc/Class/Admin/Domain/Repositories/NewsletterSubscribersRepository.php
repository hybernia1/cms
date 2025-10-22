<?php
declare(strict_types=1);

namespace Cms\Admin\Domain\Repositories;

use Core\Database\Init as DB;

final class NewsletterSubscribersRepository
{
    public function find(int $id): ?array
    {
        return DB::query()->table('newsletter_subscribers')->select(['*'])->where('id', '=', $id)->first();
    }

    public function findByEmail(string $email): ?array
    {
        return DB::query()->table('newsletter_subscribers')->select(['*'])->where('email', '=', $email)->first();
    }

    public function findByConfirmToken(string $token): ?array
    {
        return DB::query()->table('newsletter_subscribers')->select(['*'])->where('confirm_token', '=', $token)->first();
    }

    public function findByUnsubscribeToken(string $token): ?array
    {
        return DB::query()->table('newsletter_subscribers')->select(['*'])->where('unsubscribe_token', '=', $token)->first();
    }

    public function create(array $data): int
    {
        return (int) DB::query()->table('newsletter_subscribers')->insert($data)->insertGetId();
    }

    public function update(int $id, array $data): int
    {
        return DB::query()->table('newsletter_subscribers')->update($data)->where('id', '=', $id)->execute();
    }

    public function delete(int $id): int
    {
        return DB::query()->table('newsletter_subscribers')->delete()->where('id', '=', $id)->execute();
    }

    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $q = DB::query()->table('newsletter_subscribers', 'ns')->select([
            'ns.id',
            'ns.email',
            'ns.status',
            'ns.source_url',
            'ns.created_at',
            'ns.confirmed_at',
            'ns.unsubscribed_at',
        ]);

        if (!empty($filters['status'])) {
            $q->where('ns.status', '=', (string) $filters['status']);
        }

        if (!empty($filters['q'])) {
            $term = '%' . trim((string) $filters['q']) . '%';
            $q->where(function ($w) use ($term) {
                $w->whereLike('ns.email', $term)
                    ->orWhere('ns.source_url', 'LIKE', $term);
            });
        }

        $q->orderBy('ns.created_at', 'DESC');

        return $q->paginate($page, $perPage);
    }

    public function confirmedForExport(): array
    {
        return DB::query()->table('newsletter_subscribers')
            ->select(['email', 'confirmed_at', 'created_at', 'source_url'])
            ->where('status', '=', 'confirmed')
            ->orderBy('confirmed_at', 'DESC')
            ->get();
    }

    public function confirmedCount(): int
    {
        return DB::query()
            ->table('newsletter_subscribers')
            ->where('status', '=', 'confirmed')
            ->count();
    }

    public function confirmedEmails(int $limit, int $offset = 0): array
    {
        $query = DB::query()
            ->table('newsletter_subscribers')
            ->select(['id', 'email'])
            ->where('status', '=', 'confirmed')
            ->orderBy('id', 'ASC');

        if ($limit > 0) {
            $query->limit($limit);
        }

        if ($offset > 0) {
            $query->offset($offset);
        }

        return $query->get();
    }
}
