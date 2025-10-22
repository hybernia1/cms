<?php
declare(strict_types=1);

use Core\Database\Migrations\Migration;

final class _20251120_0007_posts_terms_slug_per_type extends Migration
{
    public function up(\PDO $pdo): void
    {
        $this->updatePosts($pdo);
        $this->updateTerms($pdo);
    }

    public function down(\PDO $pdo): void
    {
        $this->revertPosts($pdo);
        $this->revertTerms($pdo);
    }

    private function updatePosts(\PDO $pdo): void
    {
        if ($this->indexExists($pdo, 'posts', 'slug')) {
            $pdo->exec("ALTER TABLE posts DROP INDEX slug");
        }

        if (!$this->indexExists($pdo, 'posts', 'uq_posts_type_slug')) {
            $pdo->exec("ALTER TABLE posts ADD UNIQUE INDEX uq_posts_type_slug (type, slug)");
        }
    }

    private function updateTerms(\PDO $pdo): void
    {
        if ($this->indexExists($pdo, 'terms', 'slug')) {
            $pdo->exec("ALTER TABLE terms DROP INDEX slug");
        }

        if (!$this->indexExists($pdo, 'terms', 'uq_terms_type_slug')) {
            $pdo->exec("ALTER TABLE terms ADD UNIQUE INDEX uq_terms_type_slug (type, slug)");
        }
    }

    private function revertPosts(\PDO $pdo): void
    {
        if ($this->indexExists($pdo, 'posts', 'uq_posts_type_slug')) {
            $pdo->exec("ALTER TABLE posts DROP INDEX uq_posts_type_slug");
        }

        if (!$this->indexExists($pdo, 'posts', 'slug')) {
            $pdo->exec("ALTER TABLE posts ADD UNIQUE INDEX slug (slug)");
        }
    }

    private function revertTerms(\PDO $pdo): void
    {
        if ($this->indexExists($pdo, 'terms', 'uq_terms_type_slug')) {
            $pdo->exec("ALTER TABLE terms DROP INDEX uq_terms_type_slug");
        }

        if (!$this->indexExists($pdo, 'terms', 'slug')) {
            $pdo->exec("ALTER TABLE terms ADD UNIQUE INDEX slug (slug)");
        }
    }

    private function indexExists(\PDO $pdo, string $table, string $index): bool
    {
        $stmt = $pdo->prepare("SHOW INDEX FROM {$table} WHERE Key_name = ?");
        $stmt->execute([$index]);
        return (bool) $stmt->fetch();
    }
}
