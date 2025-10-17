<?php
declare(strict_types=1);

use Core\Database\Migrations\Migration;

final class _20251016_0006_media_add_post_id extends Migration
{
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS post_media (
  post_id BIGINT UNSIGNED NOT NULL,
  media_id BIGINT UNSIGNED NOT NULL,
  role VARCHAR(50) NOT NULL DEFAULT 'attachment',
  PRIMARY KEY (post_id, media_id),
  INDEX ix_pm_role (role),
  INDEX ix_pm_media (media_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );

        $hasPostId = $pdo->query("SHOW COLUMNS FROM media LIKE 'post_id'");
        if ($hasPostId !== false && $hasPostId->fetch()) {
            $insert = $pdo->prepare('INSERT IGNORE INTO post_media (post_id, media_id) VALUES (:post_id, :media_id)');
            $select = $pdo->query('SELECT id, post_id FROM media WHERE post_id IS NOT NULL');
            if ($select !== false) {
                while ($row = $select->fetch(\PDO::FETCH_ASSOC)) {
                    $postId = (int)($row['post_id'] ?? 0);
                    $mediaId = (int)($row['id'] ?? 0);
                    if ($postId > 0 && $mediaId > 0) {
                        $insert->execute([':post_id' => $postId, ':media_id' => $mediaId]);
                    }
                }
            }

            $index = $pdo->query("SHOW INDEX FROM media WHERE Key_name = 'ix_media_post'");
            if ($index !== false && $index->fetch()) {
                $pdo->exec("ALTER TABLE media DROP INDEX ix_media_post");
            }
            $pdo->exec("ALTER TABLE media DROP COLUMN post_id");
        }
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS post_media');

        $hasPostId = $pdo->query("SHOW COLUMNS FROM media LIKE 'post_id'");
        if ($hasPostId === false || !$hasPostId->fetch()) {
            $pdo->exec("ALTER TABLE media ADD COLUMN post_id BIGINT UNSIGNED NULL AFTER user_id");
            $pdo->exec("CREATE INDEX ix_media_post ON media (post_id)");
        }
    }
}
