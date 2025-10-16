<?php
declare(strict_types=1);

namespace Cms\Http\Admin;

use Cms\View\ViewEngine;
use Cms\Auth\AuthService;
use Core\Database\Init as DB;
use Cms\Utils\AdminNavigation;

final class ThemesController
{
    private ViewEngine $view;
    private AuthService $auth;
    private string $themesDir;

    public function __construct(string $baseViewsPath)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $this->view = new ViewEngine($baseViewsPath);
        $this->auth = new AuthService();
        $this->themesDir = dirname(__DIR__, 5) . '/themes';
        if (!is_dir($this->themesDir)) @mkdir($this->themesDir, 0775, true);
    }

    public function handle(string $action): void
    {
        switch ($action) {
            case 'activate': $this->activate(); return;
            case 'upload':   $this->upload(); return;
            case 'index':
            default:         $this->index(); return;
        }
    }

    private function token(): string
    {
        if (empty($_SESSION['csrf_admin'])) $_SESSION['csrf_admin'] = bin2hex(random_bytes(16));
        return $_SESSION['csrf_admin'];
    }
    private function assertCsrf(): void
    {
        $in = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
        if (empty($_SESSION['csrf_admin']) || !hash_equals($_SESSION['csrf_admin'], (string)$in)) {
            http_response_code(419); echo 'CSRF token invalid'; exit;
        }
    }
    private function flash(string $type, string $msg): void
    {
        $_SESSION['_flash'] = ['type'=>$type,'msg'=>$msg];
    }

    private function activeSlug(): string
    {
        $slug = DB::query()->table('settings')->select(['theme_slug'])->where('id','=',1)->value('theme_slug');
        return $slug ?: 'classic';
    }

    private function listThemes(): array
    {
        $out = [];
        foreach (glob($this->themesDir.'/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $slug = basename($dir);
            $manifest = $dir . '/theme.json';
            $meta = ['slug'=>$slug,'name'=>$slug,'version'=>'','author'=>''];
            if (is_file($manifest)) {
                $json = json_decode((string)file_get_contents($manifest), true);
                if (is_array($json)) $meta = array_merge($meta, $json);
            }
            $out[] = [
                'slug'     => (string)$meta['slug'],
                'name'     => (string)$meta['name'],
                'version'  => (string)($meta['version'] ?? ''),
                'author'   => (string)($meta['author']  ?? ''),
                'path'     => $dir,
                'screenshot' => is_file($dir.'/screenshot.png') ? 'themes/'.$slug.'/screenshot.png' : null,
                'hasTemplates' => is_dir($dir.'/templates'),
            ];
        }
        usort($out, fn($a,$b)=>strcmp($a['slug'],$b['slug']));
        return $out;
    }

    public function index(): void
    {
        $this->view->render('themes/index', [
            'pageTitle'   => 'Šablony',
            'nav'         => AdminNavigation::build('themes'),
            'currentUser' => $this->auth->user(),
            'flash'       => $_SESSION['_flash'] ?? null,
            'themes'      => $this->listThemes(),
            'activeSlug'  => $this->activeSlug(),
            'csrf'        => $this->token(),
        ]);
        unset($_SESSION['_flash']);
    }

    public function activate(): void
    {
        $this->assertCsrf();
        $slug = preg_replace('~[^a-z0-9\-_]~i','', (string)($_POST['slug'] ?? ''));
        if ($slug === '') { $this->flash('danger','Chybí slug.'); header('Location: admin.php?r=themes'); exit; }
        if (!is_dir($this->themesDir.'/'.$slug)) { $this->flash('danger','Šablona neexistuje.'); header('Location: admin.php?r=themes'); exit; }

        DB::query()->table('settings')->update([
            'theme_slug' => $slug,
            'updated_at' => date('Y-m-d H:i:s'),
        ])->where('id','=',1)->execute();

        $this->flash('success','Šablona aktivována.');
        header('Location: admin.php?r=themes');
        exit;
    }

    public function upload(): void
    {
        $this->assertCsrf();
        if (empty($_FILES['theme_zip']) || (int)$_FILES['theme_zip']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger','Soubor se nepodařilo nahrát.'); header('Location: admin.php?r=themes'); exit;
        }
        $name = (string)$_FILES['theme_zip']['name'];
        $tmp  = (string)$_FILES['theme_zip']['tmp_name'];

        if (!preg_match('~\.zip$~i', $name)) {
            $this->flash('danger','Povolen je pouze ZIP.'); header('Location: admin.php?r=themes'); exit;
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmp) !== true) {
            $this->flash('danger','ZIP nelze otevřít.'); header('Location: admin.php?r=themes'); exit;
        }

        // Zjisti kořenový adresář v ZIPu (většina šablon má root folder)
        $rootHint = null;
        for ($i=0; $i<$zip->numFiles; $i++) {
            $st = $zip->statIndex($i);
            if (!$st) continue;
            $name = $st['name'];
            if (str_contains($name, '../') || str_starts_with($name, '/')) {
                $zip->close();
                $this->flash('danger','ZIP obsahuje nebezpečné cesty.'); header('Location: admin.php?r=themes'); exit;
            }
            $parts = explode('/', $name);
            if (count($parts) > 1 && $parts[0] !== '') { $rootHint = $parts[0]; break; }
        }
        $tempDir = $this->themesDir.'/.upload_'.bin2hex(random_bytes(4));
        @mkdir($tempDir, 0775, true);
        if (!$zip->extractTo($tempDir)) {
            $zip->close();
            $this->flash('danger','Chyba při rozbalování.'); header('Location: admin.php?r=themes'); exit;
        }
        $zip->close();

        // Najdi manifest theme.json
        $candidate = $rootHint ? $tempDir.'/'.$rootHint : $tempDir;
        $manifest = null;
        if (is_file($candidate.'/theme.json')) $manifest = $candidate.'/theme.json';
        else {
            // hledej hlouběji 1. úroveň
            foreach (glob($tempDir.'/*', GLOB_ONLYDIR) ?: [] as $d) {
                if (is_file($d.'/theme.json')) { $manifest = $d.'/theme.json'; $candidate = $d; break; }
            }
        }
        if (!$manifest) {
            $this->rrmdir($tempDir);
            $this->flash('danger','Manifest theme.json nebyl nalezen.'); header('Location: admin.php?r=themes'); exit;
        }
        $info = json_decode((string)file_get_contents($manifest), true);
        if (!is_array($info) || empty($info['slug']) || empty($info['name'])) {
            $this->rrmdir($tempDir);
            $this->flash('danger','Manifest theme.json je neplatný.'); header('Location: admin.php?r=themes'); exit;
        }

        $slug = preg_replace('~[^a-z0-9\-_]~i', '', (string)$info['slug']);
        if ($slug === '') { $this->rrmdir($tempDir); $this->flash('danger','Neplatný slug v manifestu.'); header('Location: admin.php?r=themes'); exit; }

        $target = $this->themesDir.'/'.$slug;
        // přepiš existující? – povolíme (update šablony)
        if (is_dir($target)) $this->rrmdir($target);

        // Přesun
        if (!@rename($candidate, $target)) {
            // fallback: copy
            $this->rcopy($candidate, $target);
        }
        // dočasný upload adresář zruš
        $this->rrmdir($tempDir);

        $this->flash('success','Šablona nahrána: '.$slug);
        header('Location: admin.php?r=themes');
        exit;
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
    }
    private function rcopy(string $src, string $dst): void
    {
        @mkdir($dst, 0775, true);
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $file) {
            $path = $dst . DIRECTORY_SEPARATOR . $it->getSubPathName();
            if ($file->isDir()) @mkdir($path, 0775, true);
            else @copy($file->getPathname(), $path);
        }
    }
}
