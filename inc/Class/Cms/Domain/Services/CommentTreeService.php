<?php
declare(strict_types=1);

namespace Cms\Domain\Services;

use Core\Database\Init as DB;

final class CommentTreeService
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function publishedTreeForPost(int $postId): array
    {
        $rows = DB::query()->table('comments', 'c')
            ->select(['c.id','c.post_id','c.user_id','c.author_name','c.author_email','c.content','c.status','c.parent_id','c.created_at'])
            ->where('c.post_id', '=', $postId)
            ->where('c.status', '=', 'published')
            ->orderBy('c.created_at', 'ASC')
            ->get();

        $byId = [];
        foreach ($rows as $row) {
            $row['children'] = [];
            $byId[(int)$row['id']] = $row;
        }

        $root = [];
        $rootCache = [];
        $findRoot = function (int $commentId) use (&$byId, &$rootCache, &$findRoot): int {
            if (isset($rootCache[$commentId])) {
                return $rootCache[$commentId];
            }
            if (!isset($byId[$commentId])) {
                return $rootCache[$commentId] = $commentId;
            }
            $parentId = (int)($byId[$commentId]['parent_id'] ?? 0);
            if ($parentId <= 0 || !isset($byId[$parentId]) || $parentId === $commentId) {
                return $rootCache[$commentId] = $commentId;
            }
            return $rootCache[$commentId] = $findRoot($parentId);
        };

        foreach ($byId as $id => &$node) {
            $rootId = $findRoot($id);
            if ($rootId === $id) {
                $root[] = &$node;
                continue;
            }
            if (isset($byId[$rootId])) {
                $byId[$rootId]['children'][] = &$node;
            } else {
                $root[] = &$node;
            }
        }
        unset($node);

        return $root;
    }

    public function threadRootForReply(int $commentId, int $postId): int
    {
        if ($commentId <= 0) {
            return 0;
        }

        $currentId = $commentId;
        $guard = 0;
        while ($currentId > 0 && $guard < 20) {
            $row = DB::query()->table('comments')->select(['id','parent_id','post_id'])->where('id', '=', $currentId)->first();
            if (!$row || (int)($row['post_id'] ?? 0) !== $postId) {
                return 0;
            }
            $parentId = (int)($row['parent_id'] ?? 0);
            if ($parentId <= 0 || $parentId === $currentId) {
                return (int)$row['id'];
            }
            $currentId = $parentId;
            $guard++;
        }

        return 0;
    }
}
