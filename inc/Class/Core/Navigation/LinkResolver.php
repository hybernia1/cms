<?php
declare(strict_types=1);

namespace Core\Navigation;

use Cms\Admin\Utils\LinkGenerator;
use Core\Database\Init as DB;
use Throwable;

final class LinkResolver
{
    private LinkGenerator $links;

    public function __construct(?LinkGenerator $links = null)
    {
        $this->links = $links ?? new LinkGenerator();
    }

    /**
     * @param array<string,mixed> $item
     * @return array{url:string, valid:bool, type:string, reference:string, reason:?string, meta:array<string,mixed>}
     */
    public function resolve(array $item): array
    {
        $type = $this->normalizeType((string)($item['link_type'] ?? 'custom'));
        $reference = trim((string)($item['link_reference'] ?? ''));
        $fallback = (string)($item['url'] ?? '');

        return match ($type) {
            'post', 'page' => $this->resolvePostLike($type, $reference, $fallback),
            'category'     => $this->resolveCategory($reference, $fallback),
            'route'        => $this->resolveRoute($reference, $fallback),
            default        => $this->resolveCustom($fallback),
        };
    }

    private function normalizeType(string $type): string
    {
        $allowed = ['custom', 'post', 'page', 'category', 'route'];
        return in_array($type, $allowed, true) ? $type : 'custom';
    }

    /**
     * @return array{url:string, valid:bool, type:string, reference:string, reason:?string, meta:array<string,mixed>}
     */
    private function resolveCustom(string $url): array
    {
        $url = trim($url);
        $valid = $url !== '';
        return [
            'url' => $url,
            'valid' => $valid,
            'type' => 'custom',
            'reference' => '',
            'reason' => $valid ? null : 'custom-empty',
            'meta' => [],
        ];
    }

    /**
     * @return array{url:string, valid:bool, type:string, reference:string, reason:?string, meta:array<string,mixed>}
     */
    private function resolvePostLike(string $type, string $reference, string $fallback): array
    {
        $id = (int)$reference;
        if ($id <= 0) {
            return $this->invalid($type, $reference, $fallback, 'invalid-reference');
        }

        try {
            $row = DB::query()
                ->table('posts')
                ->select(['slug', 'status', 'title'])
                ->where('id', '=', $id)
                ->first();
        } catch (Throwable $e) {
            error_log('Navigation LinkResolver post lookup failed: ' . $e->getMessage());
            return $this->invalid($type, (string)$id, $fallback, 'error');
        }

        if (!$row) {
            return $this->invalid($type, (string)$id, $fallback, 'missing');
        }

        $slug = trim((string)($row['slug'] ?? ''));
        if ($slug === '') {
            return $this->invalid($type, (string)$id, $fallback, 'missing');
        }

        $status = (string)($row['status'] ?? '');
        $title = (string)($row['title'] ?? '');
        $url = $type === 'page'
            ? $this->links->page($slug)
            : $this->links->postOfType($type, $slug);
        $valid = $status === 'publish';

        return [
            'url' => $url,
            'valid' => $valid,
            'type' => $type,
            'reference' => (string)$id,
            'reason' => $valid ? null : 'unpublished',
            'meta' => [
                'slug' => $slug,
                'status' => $status,
                'title' => $title,
            ],
        ];
    }

    /**
     * @return array{url:string, valid:bool, type:string, reference:string, reason:?string, meta:array<string,mixed>}
     */
    private function resolveCategory(string $reference, string $fallback): array
    {
        $id = (int)$reference;
        if ($id <= 0) {
            return $this->invalid('category', $reference, $fallback, 'invalid-reference');
        }

        try {
            $row = DB::query()
                ->table('terms')
                ->select(['slug', 'name', 'type'])
                ->where('id', '=', $id)
                ->first();
        } catch (Throwable $e) {
            error_log('Navigation LinkResolver term lookup failed: ' . $e->getMessage());
            return $this->invalid('category', (string)$id, $fallback, 'error');
        }

        if (!$row || (string)($row['type'] ?? '') !== 'category') {
            return $this->invalid('category', (string)$id, $fallback, 'missing');
        }

        $slug = trim((string)($row['slug'] ?? ''));
        if ($slug === '') {
            return $this->invalid('category', (string)$id, $fallback, 'missing');
        }

        $url = $this->links->category($slug);

        return [
            'url' => $url,
            'valid' => true,
            'type' => 'category',
            'reference' => (string)$id,
            'reason' => null,
            'meta' => [
                'slug' => $slug,
                'title' => (string)($row['name'] ?? ''),
            ],
        ];
    }

    /**
     * @return array{url:string, valid:bool, type:string, reference:string, reason:?string, meta:array<string,mixed>}
     */
    private function resolveRoute(string $reference, string $fallback): array
    {
        $map = [
            'home' => fn(): string => $this->links->home(),
            'admin' => fn(): string => $this->links->admin(),
            'login' => fn(): string => $this->links->login(),
            'logout' => fn(): string => $this->links->logout(),
            'register' => fn(): string => $this->links->register(),
            'lost' => fn(): string => $this->links->lost(),
            'search' => fn(): string => $this->links->search(),
        ];

        $key = strtolower(trim($reference));
        if (!isset($map[$key])) {
            return $this->invalid('route', $reference, $fallback, 'unknown-route');
        }

        $url = $map[$key]();

        return [
            'url' => $url,
            'valid' => true,
            'type' => 'route',
            'reference' => $key,
            'reason' => null,
            'meta' => [
                'route' => $key,
            ],
        ];
    }

    /**
     * @return array{url:string, valid:bool, type:string, reference:string, reason:?string, meta:array<string,mixed>}
     */
    private function invalid(string $type, string $reference, string $fallback, string $reason): array
    {
        return [
            'url' => trim($fallback),
            'valid' => false,
            'type' => $this->normalizeType($type),
            'reference' => trim((string)$reference),
            'reason' => $reason,
            'meta' => [],
        ];
    }
}
