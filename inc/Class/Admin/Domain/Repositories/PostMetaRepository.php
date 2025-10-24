<?php
declare(strict_types=1);

namespace Cms\Admin\Domain\Repositories;

use Cms\Admin\Utils\DateTimeFactory;
use Core\Database\Init as DB;
use PDO;

final class PostMetaRepository
{
    /**
     * @param array<int> $postIds
     * @return array<int,array<string,array{meta_type:string,meta_value:?string}>>
     */
    public function loadForPosts(array $postIds): array
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

        $rows = DB::query()
            ->table('post_meta')
            ->select(['post_id', 'meta_key', 'meta_type', 'meta_value'])
            ->whereIn('post_id', array_values($ids))
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $postId = (int)($row['post_id'] ?? 0);
            if ($postId <= 0) {
                continue;
            }
            $key = (string)($row['meta_key'] ?? '');
            if ($key === '') {
                continue;
            }
            if (!array_key_exists($postId, $result)) {
                $result[$postId] = [];
            }
            $result[$postId][$key] = [
                'meta_type'  => (string)($row['meta_type'] ?? 'string'),
                'meta_value' => isset($row['meta_value']) ? (string)$row['meta_value'] : null,
            ];
        }

        return $result;
    }

    /**
     * @return array<string,array{meta_type:string,meta_value:?string}>
     */
    public function forPost(int $postId): array
    {
        if ($postId <= 0) {
            return [];
        }

        $all = $this->loadForPosts([$postId]);
        return $all[$postId] ?? [];
    }

    /**
     * @param array<string,array{type:string,storage:?string}> $meta
     */
    public function saveMany(int $postId, array $meta): void
    {
        if ($postId <= 0 || $meta === []) {
            return;
        }

        $sql = 'INSERT INTO post_meta (post_id, meta_key, meta_type, meta_value, created_at, updated_at)
                VALUES (:post_id, :meta_key, :meta_type, :meta_value, :created_at, :updated_at)
                ON DUPLICATE KEY UPDATE meta_type = VALUES(meta_type), meta_value = VALUES(meta_value), updated_at = VALUES(updated_at)';

        $pdo = DB::pdo();
        $stmt = $pdo->prepare($sql);
        if (!$stmt instanceof \PDOStatement) {
            throw new \RuntimeException('Failed to prepare statement for post_meta upsert.');
        }

        $now = DateTimeFactory::nowString();
        foreach ($meta as $key => $item) {
            $metaKey = '';
            if (is_string($key) && $key !== '') {
                $metaKey = $key;
            } elseif (isset($item['key']) && is_string($item['key']) && $item['key'] !== '') {
                $metaKey = $item['key'];
            }
            if ($metaKey === '') {
                continue;
            }
            $metaType = (string)($item['type'] ?? 'string');
            $metaValue = $item['storage'] ?? null;

            $stmt->bindValue(':post_id', $postId, PDO::PARAM_INT);
            $stmt->bindValue(':meta_key', $metaKey, PDO::PARAM_STR);
            $stmt->bindValue(':meta_type', $metaType, PDO::PARAM_STR);
            if ($metaValue === null) {
                $stmt->bindValue(':meta_value', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':meta_value', $metaValue, PDO::PARAM_STR);
            }
            $stmt->bindValue(':created_at', $now, PDO::PARAM_STR);
            $stmt->bindValue(':updated_at', $now, PDO::PARAM_STR);

            $stmt->execute();
        }
    }

    public function delete(int $postId, string $key): int
    {
        if ($postId <= 0 || $key === '') {
            return 0;
        }

        return DB::query()
            ->table('post_meta')
            ->delete()
            ->where('post_id', '=', $postId)
            ->where('meta_key', '=', $key)
            ->execute();
    }

    public function deleteForPost(int $postId): void
    {
        if ($postId <= 0) {
            return;
        }

        DB::query()
            ->table('post_meta')
            ->delete()
            ->where('post_id', '=', $postId)
            ->execute();
    }
}
