<?php
declare(strict_types=1);

namespace Cms\Admin\Domain\Repositories;

use Cms\Admin\Utils\DateTimeFactory;
use Core\Database\Init as DB;

final class TermsRepository
{
    public function find(int $id): ?array
    {
        return DB::query()->table('terms')->select(['*'])->where('id','=',$id)->first();
    }

    public function findBySlug(string $slug, ?string $type = null, ?int $excludeId = null): ?array
    {
        $query = DB::query()
            ->table('terms')
            ->select(['*'])
            ->where('slug', '=', $slug);

        if ($type !== null && $type !== '') {
            $query->where('type', '=', $type);
        }

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->first();
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

    /**
     * @param array<int,string> $taxonomies
     * @param array<int,string>|null $postTypes
     * @return array<int,array<string,mixed>>
     */
    public function forPublishedPosts(array $taxonomies = [], ?array $postTypes = null, ?int $limit = null): array
    {
        $now = DateTimeFactory::nowString();

        $normalizedTaxonomies = [];
        foreach ($taxonomies as $taxonomy) {
            $slug = trim((string)$taxonomy);
            if ($slug === '') {
                continue;
            }
            $normalizedTaxonomies[$slug] = $slug;
        }

        $normalizedTypes = [];
        if (is_array($postTypes)) {
            foreach ($postTypes as $type) {
                $candidate = trim((string)$type);
                if ($candidate === '') {
                    continue;
                }
                $normalizedTypes[$candidate] = $candidate;
            }
        }

        $query = DB::query()
            ->table('terms', 't')
            ->select([
                't.id',
                't.name',
                't.slug',
                't.type',
                'COUNT(DISTINCT p.id) AS post_count',
                'MAX(COALESCE(p.updated_at, p.published_at, p.created_at)) AS last_related_at',
            ])
            ->join('post_terms pt', 'pt.term_id', '=', 't.id')
            ->join('posts p', 'p.id', '=', 'pt.post_id')
            ->where('p.status', '=', 'publish')
            ->where(static function ($q) use ($now): void {
                $q->where('p.published_at', '<=', $now)
                    ->whereNull('p.published_at', 'OR');
            })
            ->groupBy(['t.id', 't.name', 't.slug', 't.type'])
            ->orderBy('t.type', 'ASC')
            ->orderBy('t.name', 'ASC');

        if ($normalizedTaxonomies !== []) {
            $query->whereIn('t.type', array_values($normalizedTaxonomies));
        }

        if ($normalizedTypes !== []) {
            $query->whereIn('p.type', array_values($normalizedTypes));
        }

        if ($limit !== null) {
            $query->limit(max(1, $limit));
        }

        return $query->get();
    }
}
