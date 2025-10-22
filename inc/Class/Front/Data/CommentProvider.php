<?php
declare(strict_types=1);

namespace Cms\Front\Data;

use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Utils\DateTimeFactory;
use Cms\Admin\Utils\RelativeDateFormatter;
use Core\Database\Init as DB;
use Throwable;

final class CommentProvider
{
    private CmsSettings $settings;
    private string $dateFormat;
    private string $timeFormat;
    private string $dateTimeFormat;
    private bool $useRelativeDates;
    private ?\DateTimeImmutable $relativeReference = null;

    public function __construct(?CmsSettings $settings = null)
    {
        $this->settings = $settings ?? new CmsSettings();
        $this->dateFormat = $this->settings->dateFormat() ?: 'Y-m-d';
        $this->timeFormat = $this->settings->timeFormat() ?: 'H:i';
        $this->dateTimeFormat = trim($this->dateFormat . ' ' . $this->timeFormat);
        if ($this->dateTimeFormat === '') {
            $this->dateTimeFormat = 'Y-m-d H:i';
        }
        $this->useRelativeDates = $this->settings->useRelativeDates();
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

        if ($this->useRelativeDates) {
            if ($this->relativeReference === null) {
                $this->relativeReference = DateTimeFactory::now();
            }
            return [
                RelativeDateFormatter::format($dateTime, $this->relativeReference),
                $dateTime->format(DATE_ATOM),
            ];
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
}
