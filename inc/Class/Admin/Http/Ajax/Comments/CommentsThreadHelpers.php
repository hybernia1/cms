<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Comments;

use Core\Database\Init as DB;

trait CommentsThreadHelpers
{
    protected function listUrl(array $params): string
    {
        $query = [
            'r'      => 'comments',
            'status' => trim((string)($params['status'] ?? '')),
            'q'      => trim((string)($params['q'] ?? '')),
            'post'   => trim((string)($params['post'] ?? '')),
        ];

        $page = isset($params['page']) ? (int)$params['page'] : 1;
        if ($page > 1) {
            $query['page'] = $page;
        }

        $query = array_filter(
            $query,
            static fn($value): bool => $value !== '' && $value !== null,
        );

        $qs = http_build_query($query);

        return $qs === '' ? 'admin.php?r=comments' : 'admin.php?' . $qs;
    }

    /**
     * @return array<int>
     */
    private function collectThreadIds(int $rootId): array
    {
        if ($rootId <= 0) {
            return [];
        }

        $ids = [$rootId];
        $queue = [$rootId];

        while ($queue !== []) {
            $rows = DB::query()->table('comments')->select(['id'])->whereIn('parent_id', $queue)->get();
            $queue = [];

            foreach ($rows as $row) {
                $childId = isset($row['id']) ? (int)$row['id'] : 0;
                if ($childId > 0 && !in_array($childId, $ids, true)) {
                    $ids[] = $childId;
                    $queue[] = $childId;
                }
            }
        }

        return $ids;
    }

    private function resolveThreadRootId(int $commentId): int
    {
        if ($commentId <= 0) {
            return 0;
        }

        $currentId = $commentId;
        $guard = 0;

        while ($currentId > 0 && $guard < 20) {
            $row = DB::query()->table('comments')->select(['id', 'parent_id'])->where('id', '=', $currentId)->first();
            if (!$row) {
                return $commentId;
            }

            $parentId = isset($row['parent_id']) ? (int)$row['parent_id'] : 0;
            if ($parentId <= 0 || $parentId === $currentId) {
                return (int)($row['id'] ?? $commentId);
            }

            $currentId = $parentId;
            $guard++;
        }

        return $commentId;
    }
}
