<?php
declare(strict_types=1);

namespace Cms\Front\Data;

use Cms\Admin\Domain\Repositories\TermsRepository;
use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Utils\DateTimeFactory;
use Cms\Admin\Utils\LinkGenerator;
use Core\Database\Init as DB;
use Throwable;

final class PostProvider
{
    private TermsRepository $terms;
    private LinkGenerator $links;
    private CmsSettings $settings;
    private string $dateFormat;
    private string $timeFormat;
    private string $dateTimeFormat;

    /** @var array<string,mixed> */
    private array $cache = [];

    public function __construct(?LinkGenerator $links = null, ?TermsRepository $terms = null, ?CmsSettings $settings = null)
    {
        $this->links = $links ?? new LinkGenerator();
        $this->terms = $terms ?? new TermsRepository();
        $this->settings = $settings ?? new CmsSettings();
        $this->dateFormat = $this->settings->dateFormat() ?: 'Y-m-d';
        $this->timeFormat = $this->settings->timeFormat() ?: 'H:i';
        $this->dateTimeFormat = trim($this->dateFormat . ' ' . $this->timeFormat);
        if ($this->dateTimeFormat === '') {
            $this->dateTimeFormat = 'Y-m-d H:i';
        }
    }

    public function findPublished(string $slug, string $type): ?array
    {
        $key = 'single:' . $type . ':' . $slug;
        if (array_key_exists($key, $this->cache)) {
            /** @var array|null $cached */
            $cached = $this->cache[$key];
            return $cached;
        }

        try {
            $row = DB::query()
                ->table('posts', 'p')
                ->select(['p.*', 'u.name AS author_name'])
                ->leftJoin('users u', 'u.id', '=', 'p.author_id')
                ->where('p.slug', '=', $slug)
                ->where('p.type', '=', $type)
                ->where('p.status', '=', 'publish')
                ->where('p.published_at', '<=', $this->now())
                ->first();
        } catch (Throwable $e) {
            error_log('Failed to load post: ' . $e->getMessage());
            $this->cache[$key] = null;
            return null;
        }

        if (!$row) {
            $this->cache[$key] = null;
            return null;
        }

        $post = $this->mapPost($row);
        $post['terms'] = $this->terms->forPost((int)$row['id']);

        $this->cache[$key] = $post;

        return $post;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function latest(string $type = 'post', int $limit = 10): array
    {
        $key = 'latest:' . $type . ':' . $limit;
        if (isset($this->cache[$key])) {
            /** @var array<int,array<string,mixed>> $cached */
            $cached = $this->cache[$key];
            return $cached;
        }

        try {
            $rows = DB::query()
                ->table('posts', 'p')
                ->select(['p.id','p.title','p.slug','p.excerpt','p.content','p.type','p.published_at','p.author_id','u.name AS author_name'])
                ->leftJoin('users u', 'u.id', '=', 'p.author_id')
                ->where('p.type', '=', $type)
                ->where('p.status', '=', 'publish')
                ->where('p.published_at', '<=', $this->now())
                ->orderBy('p.published_at', 'DESC')
                ->limit(max(1, $limit))
                ->get() ?? [];
        } catch (Throwable $e) {
            error_log('Failed to load latest posts: ' . $e->getMessage());
            $this->cache[$key] = [];
            return [];
        }

        $posts = [];
        foreach ($rows as $row) {
            $posts[] = $this->mapPost($row);
        }

        $this->cache[$key] = $posts;

        return $posts;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function forTerm(string $slug, string $termType, int $limit = 20): array
    {
        $key = 'term:' . $termType . ':' . $slug . ':' . $limit;
        if (isset($this->cache[$key])) {
            /** @var array<int,array<string,mixed>> $cached */
            $cached = $this->cache[$key];
            return $cached;
        }

        try {
            $rows = DB::query()
                ->table('posts', 'p')
                ->select(['p.id','p.title','p.slug','p.excerpt','p.content','p.type','p.published_at','p.author_id','u.name AS author_name'])
                ->join('post_terms pt', 'pt.post_id', '=', 'p.id')
                ->join('terms t', 't.id', '=', 'pt.term_id')
                ->leftJoin('users u', 'u.id', '=', 'p.author_id')
                ->where('t.slug', '=', $slug)
                ->where('t.type', '=', $termType)
                ->where('p.status', '=', 'publish')
                ->where('p.published_at', '<=', $this->now())
                ->orderBy('p.published_at', 'DESC')
                ->limit(max(1, $limit))
                ->get() ?? [];
        } catch (Throwable $e) {
            error_log('Failed to load posts for term: ' . $e->getMessage());
            $this->cache[$key] = [];
            return [];
        }

        $posts = [];
        foreach ($rows as $row) {
            $posts[] = $this->mapPost($row);
        }

        $this->cache[$key] = $posts;

        return $posts;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function search(string $term, int $limit = 20): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $key = 'search:' . md5($term) . ':' . $limit;
        if (isset($this->cache[$key])) {
            /** @var array<int,array<string,mixed>> $cached */
            $cached = $this->cache[$key];
            return $cached;
        }

        $pattern = '%' . $term . '%';
        try {
            $rows = DB::query()
                ->table('posts', 'p')
                ->select(['p.id','p.title','p.slug','p.excerpt','p.content','p.type','p.published_at','p.author_id','u.name AS author_name'])
                ->leftJoin('users u', 'u.id', '=', 'p.author_id')
                ->where('p.status', '=', 'publish')
                ->where('p.published_at', '<=', $this->now())
                ->where(function ($q) use ($pattern): void {
                    $q->whereLike('p.title', $pattern)
                        ->orWhere('p.content', 'LIKE', $pattern);
                })
                ->orderBy('p.published_at', 'DESC')
                ->limit(max(1, $limit))
                ->get() ?? [];
        } catch (Throwable $e) {
            error_log('Search query failed: ' . $e->getMessage());
            $this->cache[$key] = [];
            return [];
        }

        $posts = [];
        foreach ($rows as $row) {
            $posts[] = $this->mapPost($row);
        }

        $this->cache[$key] = $posts;

        return $posts;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function mapPost(array $row): array
    {
        $id = isset($row['id']) ? (int)$row['id'] : 0;
        $slug = (string)($row['slug'] ?? '');
        $type = (string)($row['type'] ?? 'post');
        $content = (string)($row['content'] ?? '');
        $commentsAllowed = null;
        if (array_key_exists('comments_allowed', $row)) {
            $commentsAllowed = (int)$row['comments_allowed'] === 1;
        }

        $permalink = match ($type) {
            'page' => $this->links->page($slug),
            default => $this->links->post($slug),
        };

        $excerpt = (string)($row['excerpt'] ?? '');
        if ($excerpt === '') {
            $excerpt = $this->excerptFromContent($content);
        }

        $publishedRaw = isset($row['published_at']) ? (string)$row['published_at'] : '';
        [$publishedDisplay, $publishedIso] = $this->normalizeDate($publishedRaw);

        $authorId = isset($row['author_id']) ? (int)$row['author_id'] : 0;
        $authorId = $authorId > 0 ? $authorId : null;
        $authorName = trim((string)($row['author_name'] ?? ''));

        return [
            'id' => $id,
            'title' => (string)($row['title'] ?? ''),
            'slug' => $slug,
            'type' => $type,
            'content' => $content,
            'excerpt' => $excerpt,
            'author' => $authorName,
            'author_id' => $authorId,
            'published_at' => $publishedDisplay,
            'published_at_iso' => $publishedIso,
            'published_at_raw' => $publishedRaw,
            'permalink' => $permalink,
            'comments_allowed' => $commentsAllowed,
        ];
    }

    private function now(): string
    {
        return DateTimeFactory::nowString();
    }

    /**
     * @return array{0:string,1:string}
     */
    private function normalizeDate(?string $value): array
    {
        $raw = $value !== null ? trim((string)$value) : '';
        if ($raw === '') {
            return ['', ''];
        }

        $dateTime = DateTimeFactory::fromStorage($raw);
        if ($dateTime === null) {
            return ['', ''];
        }

        $format = $this->dateTimeFormat !== '' ? $this->dateTimeFormat : 'Y-m-d H:i';

        return [
            $dateTime->format($format),
            $dateTime->format(DATE_ATOM),
        ];
    }

    private function excerptFromContent(string $content, int $limit = 200): string
    {
        $text = trim(strip_tags($content));
        if ($text === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text) <= $limit) {
                return $text;
            }
            return rtrim(mb_substr($text, 0, $limit - 1)) . '…';
        }

        if (strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(substr($text, 0, $limit - 1)) . '…';
    }
}
