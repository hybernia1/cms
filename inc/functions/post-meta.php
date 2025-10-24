<?php
declare(strict_types=1);

use Cms\Admin\Domain\PostMeta\PostMetaRegistry;
use Cms\Admin\Domain\Repositories\PostMetaRepository;
use Core\Database\Init as DB;

/**
 * @return array<int,string>
 */
function &cms_post_meta_type_cache_storage(): array
{
    static $cache = [];

    return $cache;
}

/**
 * @param array<string,mixed> $args
 */
function register_post_meta(string $postType, string $key, array $args = []): void
{
    PostMetaRegistry::register($postType, $key, $args);
}

/**
 * @param array<string,mixed> $args
 */
function register_shared_post_meta(string $key, array $args = []): void
{
    PostMetaRegistry::registerShared($key, $args);
}

function get_post_meta(int $postId, string $key, mixed $default = null): mixed
{
    $normalizedKey = trim($key);
    if ($normalizedKey === '') {
        return $default;
    }

    $all = get_posts_meta([$postId]);
    if (!isset($all[$postId])) {
        return $default;
    }

    if (array_key_exists($normalizedKey, $all[$postId])) {
        return $all[$postId][$normalizedKey];
    }

    return $default;
}

/**
 * @param array<int> $postIds
 * @return array<int,array<string,mixed>>
 */
function get_posts_meta(array $postIds): array
{
    if ($postIds === []) {
        return [];
    }

    $ids = [];
    foreach ($postIds as $id) {
        $value = (int)$id;
        if ($value > 0) {
            $ids[$value] = $value;
        }
    }
    if ($ids === []) {
        return [];
    }

    $cache =& cms_post_meta_type_cache_storage();
    $types = [];
    $missing = [];
    foreach ($ids as $id) {
        if (isset($cache[$id])) {
            $types[$id] = $cache[$id];
        } else {
            $missing[] = $id;
        }
    }

    if ($missing !== []) {
        $rows = DB::query()
            ->table('posts')
            ->select(['id', 'type'])
            ->whereIn('id', $missing)
            ->get();
        foreach ($rows as $row) {
            $postId = (int)($row['id'] ?? 0);
            if ($postId <= 0) {
                continue;
            }
            $type = trim((string)($row['type'] ?? ''));
            if ($type === '') {
                $type = 'post';
            }
            $cache[$postId] = $type;
            $types[$postId] = $type;
        }
    }

    if ($types === []) {
        return [];
    }

    $repo = new PostMetaRepository();
    $raw = $repo->loadForPosts(array_keys($types));
    $result = [];
    foreach ($types as $postId => $type) {
        $rows = $raw[$postId] ?? [];
        $result[$postId] = PostMetaRegistry::hydrateAll($type, $rows);
    }

    return $result;
}

function update_post_meta(int $postId, string $key, mixed $value, ?string $postType = null): void
{
    if ($postId <= 0) {
        return;
    }

    $type = $postType !== null ? trim($postType) : '';
    if ($type === '') {
        $type = cms_resolve_post_meta_type($postId);
        if ($type === null) {
            return;
        }
    } else {
        cms_cache_post_meta_type($postId, $type);
    }

    $prepared = PostMetaRegistry::prepareForStorage($type, $key, $value);
    $repo = new PostMetaRepository();
    $repo->saveMany($postId, [
        $prepared['key'] => [
            'key'     => $prepared['key'],
            'type'    => $prepared['type'],
            'storage' => $prepared['storage'],
        ],
    ]);
}

function delete_post_meta(int $postId, string $key): void
{
    if ($postId <= 0) {
        return;
    }
    $normalizedKey = trim($key);
    if ($normalizedKey === '') {
        return;
    }

    $repo = new PostMetaRepository();
    $repo->delete($postId, $normalizedKey);
}

function cms_resolve_post_meta_type(int $postId): ?string
{
    $cache =& cms_post_meta_type_cache_storage();

    if (isset($cache[$postId])) {
        return $cache[$postId];
    }

    $row = DB::query()
        ->table('posts')
        ->select(['type'])
        ->where('id', '=', $postId)
        ->first();
    if (!$row) {
        return null;
    }
    $type = trim((string)($row['type'] ?? ''));
    if ($type === '') {
        $type = 'post';
    }
    $cache[$postId] = $type;

    return $type;
}

function cms_cache_post_meta_type(int $postId, string $type): void
{
    $cache =& cms_post_meta_type_cache_storage();
    $normalized = trim($type);
    if ($postId <= 0 || $normalized === '') {
        return;
    }
    $cache[$postId] = $normalized;
}
