<?php
declare(strict_types=1);

namespace Cms\Front\View;

use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\View\ViewEngine;
use Cms\Admin\Utils\LinkGenerator;

final class ThemeViewEngine
{
    private ViewEngine $engine;
    private CmsSettings $settings;
    private LinkGenerator $links;
    private string $themeSlug;
    private string $fallbackSlug;
    private string $baseDir;

    public function __construct(?CmsSettings $settings = null, ?LinkGenerator $links = null, string $fallbackSlug = 'classic')
    {
        $this->settings = $settings ?? new CmsSettings();
        $this->links = $links ?? new LinkGenerator();
        $this->fallbackSlug = $fallbackSlug;
        $this->baseDir = defined('BASE_DIR') ? BASE_DIR : __DIR__ . '/../../../..';

        $this->engine = new ViewEngine($this->baseDir . '/themes');
        $this->setTheme($this->settings->themeSlug());
        $this->shareDefaults();
    }

    public function setTheme(string $slug): void
    {
        $slug = $slug !== '' ? $slug : 'classic';
        $this->themeSlug = $slug;

        $paths = [];
        $themePath = $this->baseDir . '/themes/' . $slug . '/templates';
        if (is_dir($themePath)) {
            $paths[] = $themePath;
        } else {
            error_log("Theme '{$slug}' is missing templates directory: {$themePath}");
        }

        if ($this->fallbackSlug !== $slug) {
            $fallbackPath = $this->baseDir . '/themes/' . $this->fallbackSlug . '/templates';
            if (is_dir($fallbackPath)) {
                $paths[] = $fallbackPath;
            }
        }

        $builtin = $this->baseDir . '/inc/resources/templates/simple';
        if (is_dir($builtin)) {
            $paths[] = $builtin;
        }

        if ($paths === []) {
            throw new \RuntimeException('No template directories available for front-end rendering.');
        }

        $this->engine->setBasePaths($paths);
        $this->loadThemeFunctions($slug);
    }

    public function share(array $data): void
    {
        $this->engine->share($data);
    }

    public function render(string $template, array $data = []): void
    {
        $this->engine->render($template, $data);
    }

    public function renderWithLayout(?string $layout, string $template, array $data = []): void
    {
        if ($layout === null) {
            $this->render($template, $data);
            return;
        }

        $this->engine->render($layout, $data, function () use ($template, $data): void {
            $this->engine->render($template, $data);
        });
    }

    public function themeSlug(): string
    {
        return $this->themeSlug;
    }

    public function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $base = $this->basePath();
        return $base . '/themes/' . $this->themeSlug . '/' . $path;
    }

    private function basePath(): string
    {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = str_replace('\\', '/', (string)dirname($script));
        $dir = rtrim($dir, '/');
        if ($dir === '' || $dir === '.') {
            return '';
        }
        return $dir;
    }

    private function shareDefaults(): void
    {
        $siteTitle = $this->settings->siteTitle();
        $this->share([
            'site' => [
                'title' => $siteTitle,
                'url' => $this->settings->siteUrl(),
            ],
            'links' => $this->links,
            'theme' => [
                'slug' => $this->themeSlug,
                'asset' => fn (string $file): string => $this->asset($file),
            ],
            'navigation' => [],
            'meta' => [
                'title' => $siteTitle,
                'description' => null,
                'canonical' => null,
                'extra' => [],
            ],
        ]);
    }

    private function loadThemeFunctions(string $slug): void
    {
        $file = $this->baseDir . '/themes/' . $slug . '/functions.php';
        if (is_file($file)) {
            try {
                require_once $file;
            } catch (\Throwable $e) {
                error_log('Theme helper bootstrap failed: ' . $e->getMessage());
            }
        }
    }
}
