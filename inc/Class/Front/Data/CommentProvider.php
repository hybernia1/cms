<?php
declare(strict_types=1);

namespace Cms\Front\Data;

use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Utils\LinkGenerator;
use Cms\Admin\Utils\DateTimeFactory;
use Core\Database\Init as DB;
use Throwable;

final class CommentProvider
{
    private CmsSettings $settings;
    private LinkGenerator $links;
    private string $dateFormat;
    private string $timeFormat;
    private string $dateTimeFormat;

    public function __construct(?CmsSettings $settings = null, ?LinkGenerator $links = null)
    {
        $this->settings = $settings ?? new CmsSettings();
        $this->links = $links ?? new LinkGenerator(null, $this->settings);
        $this->dateFormat = $this->settings->dateFormat() ?: 'Y-m-d';
        $this->timeFormat = $this->settings->timeFormat() ?: 'H:i';
        $this->dateTimeFormat = trim($this->dateFormat . ' ' . $this->timeFormat);
        if ($this->dateTimeFormat === '') {
            $this->dateTimeFormat = 'Y-m-d H:i';
        }
    }

    /**
     * @return array{items:array<int,array<string,mixed>>,total:int}
     */
    public function publishedByUser(int $userId, int $limit = 20): array
    {
        if ($userId <= 0) {
            return ['items' => [], 'total' => 0];
        }

        try {
            $rows = DB::query()
                ->table('comments', 'c')
                ->select([
                    'c.id',
                    'c.post_id',
                    'c.content',
                    'c.created_at',
                    'p.title AS post_title',
                    'p.slug AS post_slug',
                    'p.type AS post_type',
                    'p.status AS post_status',
                ])
                ->leftJoin('posts p', 'p.id', '=', 'c.post_id')
                ->where('c.user_id', '=', $userId)
                ->where('c.status', '=', 'published')
                ->orderBy('c.created_at', 'DESC')
                ->limit(max(1, $limit))
                ->get() ?? [];
        } catch (Throwable $e) {
            error_log('Failed to load user comments: ' . $e->getMessage());
            return ['items' => [], 'total' => 0];
        }

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->mapUserComment($row);
        }

        return ['items' => $items, 'total' => count($items)];
    }

    /**
     * @return array{items:array<int,array<string,mixed>>,total:int}
     */
    public function publishedForPost(int $postId): array
    {
        if ($postId <= 0) {
            return ['items' => [], 'total' => 0];
        }

        try {
            $rows = DB::query()
                ->table('comments', 'c')
                ->select(['c.id','c.parent_id','c.author_name','c.content','c.created_at'])
                ->where('c.post_id', '=', $postId)
                ->where('c.status', '=', 'published')
                ->orderBy('c.created_at', 'ASC')
                ->get() ?? [];
        } catch (Throwable $e) {
            error_log('Failed to load comments: ' . $e->getMessage());
            return ['items' => [], 'total' => 0];
        }

        $mapped = [];
        foreach ($rows as $row) {
            $mapped[] = $this->mapComment($row);
        }

        $tree = $this->buildTree($mapped);

        return ['items' => $tree, 'total' => count($mapped)];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function mapComment(array $row): array
    {
        $id = isset($row['id']) ? (int)$row['id'] : 0;
        $parent = isset($row['parent_id']) ? (int)$row['parent_id'] : 0;
        $parent = $parent > 0 ? $parent : null;

        $createdRaw = isset($row['created_at']) ? (string)$row['created_at'] : '';
        [$createdDisplay, $createdIso] = $this->normalizeDate($createdRaw);

        $author = trim((string)($row['author_name'] ?? ''));
        if ($author === '') {
            $author = 'Anonym';
        }

        return [
            'id' => $id,
            'parent_id' => $parent,
            'author' => $author,
            'content' => (string)($row['content'] ?? ''),
            'created_at' => $createdDisplay,
            'created_at_iso' => $createdIso,
            'created_at_raw' => $createdRaw,
        ];
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

    /**
     * @param array<int,array<string,mixed>> $comments
     * @return array<int,array<string,mixed>>
     */
    private function buildTree(array $comments): array
    {
        if ($comments === []) {
            return [];
        }

        $byId = [];
        $childrenMap = [];

        foreach ($comments as $comment) {
            $id = (int)($comment['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $byId[$id] = $comment;
            $childrenMap[$id] = [];
        }

        foreach ($byId as $id => $comment) {
            $parentId = isset($comment['parent_id']) ? (int)$comment['parent_id'] : 0;
            if ($parentId > 0 && isset($byId[$parentId])) {
                $childrenMap[$parentId][] = $id;
            }
        }

        $buildNode = function (int $id, int $rootId) use (&$byId, &$childrenMap, &$buildNode): array {
            $node = $byId[$id];
            $node['thread_root_id'] = $rootId;
            $children = [];
            foreach ($childrenMap[$id] as $childId) {
                $children[] = $buildNode($childId, $rootId);
            }
            $node['children'] = $children;
            return $node;
        };

        $roots = [];
        foreach ($byId as $id => $comment) {
            $parentId = isset($comment['parent_id']) ? (int)$comment['parent_id'] : 0;
            if ($parentId === 0 || !isset($byId[$parentId])) {
                $roots[] = $buildNode($id, $id);
            }
        }

        return $roots;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function mapUserComment(array $row): array
    {
        $id = isset($row['id']) ? (int)$row['id'] : 0;
        $postId = isset($row['post_id']) ? (int)$row['post_id'] : 0;
        $content = (string)($row['content'] ?? '');

        $createdRaw = isset($row['created_at']) ? (string)$row['created_at'] : '';
        [$createdDisplay, $createdIso] = $this->normalizeDate($createdRaw);

        $postTitle = (string)($row['post_title'] ?? '');
        $postSlug = (string)($row['post_slug'] ?? '');
        $postType = (string)($row['post_type'] ?? 'post');
        $postStatus = (string)($row['post_status'] ?? '');

        $postUrl = '';
        if ($postSlug !== '' && $postStatus === 'publish') {
            $postUrl = $this->links->postOfType($postType !== '' ? $postType : 'post', $postSlug);
        }

        return [
            'id' => $id,
            'post_id' => $postId,
            'content' => $content,
            'created_at' => $createdDisplay,
            'created_at_iso' => $createdIso,
            'created_at_raw' => $createdRaw,
            'post_title' => $postTitle,
            'post_slug' => $postSlug,
            'post_type' => $postType,
            'post_status' => $postStatus,
            'post_url' => $postUrl,
        ];
    }
}
