<?php
declare(strict_types=1);

use Core\Database\Migrations\Migration;

final class _20251016_0003_settings_registration_siteurl extends Migration
{
    public function up(\PDO $pdo): void
    {
        $cols = $this->getColumns($pdo,'settings');

        if (!in_array('allow_registration', $cols, true)) {
            $pdo->exec("ALTER TABLE settings ADD COLUMN allow_registration TINYINT(1) NOT NULL DEFAULT 1");
        }
        if (!in_array('site_url', $cols, true)) {
            $pdo->exec("ALTER TABLE settings ADD COLUMN site_url VARCHAR(255) NOT NULL DEFAULT ''");
        }

        // Auto-detect site_url pokud chybí
        $stmt = $pdo->query("SELECT site_url FROM settings WHERE id=1");
        $row  = $stmt->fetch(\PDO::FETCH_ASSOC);
        $site = (string)($row['site_url'] ?? '');
        if ($site === '') {
            $guess = $this->guessSiteUrl();
            $upd = $pdo->prepare("UPDATE settings SET site_url=?, updated_at=NOW() WHERE id=1");
            $upd->execute([$guess]);
        }
    }

    public function down(\PDO $pdo): void
    {
        // Necháme – většinou nevracíme zpět
        // $pdo->exec("ALTER TABLE settings DROP COLUMN site_url");
        // $pdo->exec("ALTER TABLE settings DROP COLUMN allow_registration");
    }

    private function getColumns(\PDO $pdo, string $table): array
    {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        $cols = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) { $cols[] = (string)$r['Field']; }
        return $cols;
    }

    private function guessSiteUrl(): string
    {
        // jednoduché odvození – použij při migraci CLI/HTTP proměnné, co jsou k dispozici
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                   || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
        $scheme  = $isHttps ? 'https' : 'http';
        $host    = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $base    = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        $path    = $base && $base !== '/' ? $base : '';
        return "{$scheme}://{$host}{$path}";
    }
}
