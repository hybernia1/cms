<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Controllers;

use Cms\Admin\Utils\AdminNavigation;
use Cms\Admin\Utils\DateTimeFactory;
use Core\Database\Init as DB;

final class ThemesController extends BaseAdminController
{
    private string $themesDir;

    public function __construct(string $baseViewsPath)
    {
        parent::__construct($baseViewsPath);
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

    private function activeSlug(): string
    {
        $slug = DB::query()->table('settings')->select(['theme_slug'])->where('id','=',1)->value('theme_slug');
        return trim((string)$slug);
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
        $this->renderAdmin('themes/index', [
            'pageTitle'  => 'Šablony',
            'nav'        => AdminNavigation::build('themes'),
            'themes'     => $this->listThemes(),
            'activeSlug' => $this->activeSlug(),
        ]);
    }

    public function activate(): void
    {
        $this->assertCsrf();
        $slug = preg_replace('~[^a-z0-9\-_]~i','', (string)($_POST['slug'] ?? ''));
        if ($slug === '') {
            $this->respondThemesError('Chybí slug.');
        }
        if (!is_dir($this->themesDir.'/'.$slug)) {
            $this->respondThemesError('Šablona neexistuje.');
        }

        $previousSlug = $this->activeSlug();

        DB::query()->table('settings')->update([
            'theme_slug' => $slug,
            'updated_at' => DateTimeFactory::nowString(),
        ])->where('id','=',1)->execute();

        $payload = [
            'themes'     => $this->listThemes(),
            'activeSlug' => $slug,
            'previousSlug' => $previousSlug,
            'csrf'       => $this->token(),
        ];

        $this->respondThemesSuccess($payload, 'Šablona aktivována.');
    }

    public function upload(): void
    {
        $this->assertCsrf();
        if (empty($_FILES['theme_zip']) || (int)$_FILES['theme_zip']['error'] !== UPLOAD_ERR_OK) {
            $this->respondThemesError('Soubor se nepodařilo nahrát.');
        }
        $name = (string)$_FILES['theme_zip']['name'];
        $tmp  = (string)$_FILES['theme_zip']['tmp_name'];

        if (!preg_match('~\.zip$~i', $name)) {
            $this->respondThemesError('Povolen je pouze ZIP.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmp) !== true) {
            $this->respondThemesError('ZIP nelze otevřít.');
        }

        // Zjisti kořenový adresář v ZIPu (většina šablon má root folder)
        $rootHint = null;
        for ($i=0; $i<$zip->numFiles; $i++) {
            $st = $zip->statIndex($i);
            if (!$st) continue;
            $name = $st['name'];
            if (str_contains($name, '../') || str_starts_with($name, '/')) {
                $zip->close();
                $this->respondThemesError('ZIP obsahuje nebezpečné cesty.');
            }
            $parts = explode('/', $name);
            if (count($parts) > 1 && $parts[0] !== '') { $rootHint = $parts[0]; break; }
        }
        $tempDir = $this->themesDir.'/.upload_'.bin2hex(random_bytes(4));
        @mkdir($tempDir, 0775, true);
        if (!$zip->extractTo($tempDir)) {
            $zip->close();
            $this->respondThemesError('Chyba při rozbalování.');
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
            $this->respondThemesError('Manifest theme.json nebyl nalezen.');
        }
        $info = json_decode((string)file_get_contents($manifest), true);
        if (!is_array($info) || empty($info['slug']) || empty($info['name'])) {
            $this->rrmdir($tempDir);
            $this->respondThemesError('Manifest theme.json je neplatný.');
        }

        $slug = preg_replace('~[^a-z0-9\-_]~i', '', (string)$info['slug']);
        if ($slug === '') {
            $this->rrmdir($tempDir);
            $this->respondThemesError('Neplatný slug v manifestu.');
        }

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

        $themes = $this->listThemes();
        $newTheme = null;
        foreach ($themes as $theme) {
            if ($theme['slug'] === $slug) {
                $newTheme = $theme;
                break;
            }
        }

        $payload = [
            'themes'     => $themes,
            'theme'      => $newTheme,
            'activeSlug' => $this->activeSlug(),
            'csrf'       => $this->token(),
        ];

        $this->respondThemesSuccess($payload, 'Šablona nahrána: '.$slug);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function respondThemesSuccess(array $data, string $message, string $type = 'success'): void
    {
        if (!$this->isAjax()) {
            $this->flash($type, $message);
            $this->redirect('admin.php?r=themes');
        }

        $payload = array_merge(['success' => true], $data);
        if ($message !== '') {
            $payload['flash'] = [
                'type' => $type,
                'msg'  => $message,
            ];
        }

        $this->jsonResponse($payload);
    }

    private function respondThemesError(string $message, int $status = 400): void
    {
        if (!$this->isAjax()) {
            $this->flash('danger', $message);
            $this->redirect('admin.php?r=themes');
        }

        $this->jsonResponse([
            'success' => false,
            'flash'   => [
                'type' => 'danger',
                'msg'  => $message,
            ],
        ], $status);
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
