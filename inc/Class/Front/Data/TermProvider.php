<?php
declare(strict_types=1);

namespace Cms\Front\Data;

use Cms\Admin\Domain\Repositories\TermsRepository;
use Core\Database\Init as DB;
use Throwable;

final class TermProvider
{
    private TermsRepository $terms;

    /** @var array<string,mixed> */
    private array $cache = [];

    public function __construct(?TermsRepository $terms = null)
    {
        $this->terms = $terms ?? new TermsRepository();
    }

    public function findBySlug(string $slug, ?string $type = null): ?array
    {
        $key = 'term:' . ($type ?? '*') . ':' . $slug;
        if (array_key_exists($key, $this->cache)) {
            /** @var array|null $cached */
            $cached = $this->cache[$key];
            return $cached;
        }

        try {
            $term = $this->terms->findBySlug($slug, $type);
        } catch (Throwable $e) {
            error_log('Failed to load term: ' . $e->getMessage());
            $this->cache[$key] = null;
            return null;
        }
        if (!$term) {
            $this->cache[$key] = null;
            return null;
        }

        if ($type !== null && isset($term['type']) && (string)$term['type'] !== $type) {
            $this->cache[$key] = null;
            return null;
        }

        $this->cache[$key] = $term;

        return $term;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function children(int $termId): array
    {
        $key = 'children:' . $termId;
        if (isset($this->cache[$key])) {
            /** @var array<int,array<string,mixed>> $cached */
            $cached = $this->cache[$key];
            return $cached;
        }

        try {
            $rows = DB::query()
                ->table('terms')
                ->select(['id','name','slug','type','parent_id'])
                ->where('parent_id', '=', $termId)
                ->orderBy('name', 'ASC')
                ->get() ?? [];
        } catch (Throwable $e) {
            error_log('Failed to load term children: ' . $e->getMessage());
            $this->cache[$key] = [];
            return [];
        }

        $this->cache[$key] = $rows;

        return $rows;
    }
}
