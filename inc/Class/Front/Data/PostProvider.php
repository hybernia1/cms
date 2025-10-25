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

        $now = $this->now();

        try {
            $row = DB::query()
                ->table('posts', 'p')
                ->select([
                    'p.*',
                    'u.name AS author_name',
                    'm.url AS thumbnail_url',
                    'm.meta AS thumbnail_meta',
                    'm.mime AS thumbnail_mime',
                ])
                ->leftJoin('media m', 'm.id', '=', 'p.thumbnail_id')
                ->leftJoin('users u', 'u.id', '=', 'p.author_id')
                ->where('p.slug', '=', $slug)
                ->where('p.type', '=', $type)
                ->where('p.status', '=', 'publish')
                ->where(static function ($q) use ($now): void {
                    $q->where('p.published_at', '<=', $now)
                        ->whereNull('p.published_at', 'OR');
                })
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

        $now = $this->now();

        try {
            $rows = DB::query()
                ->table('posts', 'p')
                ->select([
                    'p.id',
                    'p.title',
                    'p.slug',
                    'p.content',
                    'p.type',
                    'p.published_at',
                    'p.author_id',
                    'p.thumbnail_id',
                    'u.name AS author_name',
                    'm.url AS thumbnail_url',
                    'm.meta AS thumbnail_meta',
                    'm.mime AS thumbnail_mime',
                ])
                ->leftJoin('media m', 'm.id', '=', 'p.thumbnail_id')
                ->leftJoin('users u', 'u.id', '=', 'p.author_id')
                ->where('p.type', '=', $type)
                ->where('p.status', '=', 'publish')
                ->where(static function ($q) use ($now): void {
                    $q->where('p.published_at', '<=', $now)
                        ->whereNull('p.published_at', 'OR');
                })
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

        $now = $this->now();

        try {
            $rows = DB::query()
                ->table('posts', 'p')
                ->select([
                    'p.id',
                    'p.title',
                    'p.slug',
                    'p.content',
                    'p.type',
                    'p.published_at',
                    'p.author_id',
                    'p.thumbnail_id',
                    'u.name AS author_name',
                    'm.url AS thumbnail_url',
                    'm.meta AS thumbnail_meta',
                    'm.mime AS thumbnail_mime',
                ])
                ->join('post_terms pt', 'pt.post_id', '=', 'p.id')
                ->join('terms t', 't.id', '=', 'pt.term_id')
                ->leftJoin('users u', 'u.id', '=', 'p.author_id')
                ->leftJoin('media m', 'm.id', '=', 'p.thumbnail_id')
                ->where('t.slug', '=', $slug)
                ->where('t.type', '=', $termType)
                ->where('p.status', '=', 'publish')
                ->where(static function ($q) use ($now): void {
                    $q->where('p.published_at', '<=', $now)
                        ->whereNull('p.published_at', 'OR');
                })
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
        $now = $this->now();

        try {
            $rows = DB::query()
                ->table('posts', 'p')
                ->select([
                    'p.id',
                    'p.title',
                    'p.slug',
                    'p.content',
                    'p.type',
                    'p.published_at',
                    'p.author_id',
                    'p.thumbnail_id',
                    'u.name AS author_name',
                    'm.url AS thumbnail_url',
                    'm.meta AS thumbnail_meta',
                    'm.mime AS thumbnail_mime',
                ])
                ->leftJoin('media m', 'm.id', '=', 'p.thumbnail_id')
                ->leftJoin('users u', 'u.id', '=', 'p.author_id')
                ->where('p.status', '=', 'publish')
                ->where(static function ($q) use ($now): void {
                    $q->where('p.published_at', '<=', $now)
                        ->whereNull('p.published_at', 'OR');
                })
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

        $permalink = $type === 'page'
            ? $this->links->page($slug)
            : $this->links->postOfType($type, $slug);

        $excerpt = (string)($row['excerpt'] ?? '');
        if ($excerpt === '') {
            $excerpt = $this->excerptFromContent($content);
        }

        $publishedRaw = isset($row['published_at']) ? (string)$row['published_at'] : '';
        [$publishedDisplay, $publishedIso] = $this->normalizeDate($publishedRaw);

        $authorId = isset($row['author_id']) ? (int)$row['author_id'] : 0;
        $authorId = $authorId > 0 ? $authorId : null;
        $authorName = trim((string)($row['author_name'] ?? ''));

        $thumbnailId = isset($row['thumbnail_id']) ? (int)$row['thumbnail_id'] : 0;
        $thumbnailUrl = (string)($row['thumbnail_url'] ?? '');
        $thumbnailMime = (string)($row['thumbnail_mime'] ?? '');
        $thumbnailMeta = $this->normalizeThumbnailMeta($row['thumbnail_meta'] ?? null);
        $thumbnail = null;
        if ($thumbnailId > 0 && $thumbnailUrl !== '') {
            $thumbnail = [
                'id' => $thumbnailId,
                'url' => $thumbnailUrl,
                'mime' => $thumbnailMime,
            ];
            if ($thumbnailMeta !== []) {
                $thumbnail['meta'] = $thumbnailMeta;
            }
        }

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
            'thumbnail_id' => $thumbnailId > 0 ? $thumbnailId : null,
            'thumbnail_url' => $thumbnail !== null ? $thumbnailUrl : '',
            'thumbnail_meta' => $thumbnail !== null ? $thumbnailMeta : [],
            'thumbnail' => $thumbnail,
        ];
    }

    private function now(): string
    {
        return DateTimeFactory::nowString();
    }

    /**
     * @param mixed $value
     * @return array<string,mixed>
     */
    private function normalizeThumbnailMeta($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value)) {
            return [];
        }

        $raw = trim($value);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
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
