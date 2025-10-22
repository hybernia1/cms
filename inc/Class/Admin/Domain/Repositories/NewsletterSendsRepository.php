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
