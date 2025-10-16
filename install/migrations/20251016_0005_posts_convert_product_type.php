<?php
declare(strict_types=1);

use Core\Database\Migrations\Migration;

final class _20251016_0005_posts_convert_product_type extends Migration
{
    public function up(\PDO $pdo): void
    {
        $stmt = $pdo->prepare("UPDATE posts SET type = 'post' WHERE type = 'product'");
        $stmt->execute();
    }

    public function down(\PDO $pdo): void
    {
        // Produktový typ byl odstraněn bez možnosti spolehlivého návratu.
    }
}
