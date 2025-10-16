<?php
declare(strict_types=1);

namespace Cms\Theming;

use Cms\Settings\CmsSettings;

final class ThemeManager
{
    private string $themesBase;
    private string $active;
    /** @var array<string,mixed> */
    private array $manifest = [];
    /** @var string[] */
    private array $hierarchy = [];

    public function __construct(?string $forceSlug = null)
    {
        // /themes
        $this->themesBase = dirname(__DIR__, 4) . '/themes';

        if ($forceSlug !== null && $forceSlug !== '') {
            $this->active = $forceSlug;
        } else {
            $settings = new CmsSettings();
            // preferuj nové API:
            if (method_exists($settings, 'themeSlug')) {
                $this->active = $settings->themeSlug() ?: 'classic';
            } else {
                // fallback na staré API (pokud by existovalo v jiné větvi)
                $this->active = method_exists($settings, 'getActiveTheme')
                    ? $settings->getActiveTheme()
                    : 'classic';
            }
        }

        $this->hierarchy = [$this->active];
        $this->loadManifest();
    }

    /** Slug aktivní šablony */
    public function activeSlug(): string
    {
        return $this->active;
    }

    /** Absolutní cesta k kořeni aktivní šablony (/themes/{slug}) */
    public function activePath(): string
    {
        return rtrim($this->themesBase, '/').'/'.$this->active;
    }

    /** Absolutní cesta k templates (/themes/{slug}/templates) */
    public function templateBasePath(): string
    {
        $bases = $this->templateBases();
        if ($bases !== []) {
            return $bases[0];
        }
        return $this->activePath().'/templates';
    }

    /** @return array<string,mixed> */
    public function manifest(): array
    {
        return $this->manifest;
    }

    /** @return string[] */
    public function hierarchy(): array
    {
        return $this->hierarchy;
    }

    /** @return string[] */
    public function templateBases(): array
    {
        $bases = [];
        foreach ($this->hierarchy as $slug) {
            $candidate = rtrim($this->themesBase, '/').'/'.$slug.'/templates';
            if (is_dir($candidate)) {
                $bases[] = $candidate;
            }
        }
        return $bases;
    }

    /** @return array<int,array{slug:string,path:string}> */
    public function themeRoots(): array
    {
        $roots = [];
        foreach ($this->hierarchy as $slug) {
            $path = rtrim($this->themesBase, '/').'/'.$slug;
            if (is_dir($path)) {
                $roots[] = [
                    'slug' => $slug,
                    'path' => $path,
                ];
            }
        }
        return $roots;
    }

    private function loadManifest(): void
    {
        $manifestFile = $this->activePath().'/theme.json';
        if (!is_file($manifestFile)) {
            return;
        }

        $decoded = json_decode((string)@file_get_contents($manifestFile), true);
        if (!is_array($decoded)) {
            return;
        }

        $this->manifest = $decoded;

        $parent = (string)($decoded['parent'] ?? '');
        if ($parent !== '') {
            $this->appendParentHierarchy($parent);
        }
    }

    private function appendParentHierarchy(string $parent): void
    {
        $current = $parent;
        while ($current !== '' && !in_array($current, $this->hierarchy, true)) {
            $path = rtrim($this->themesBase, '/').'/'.$current;
            if (!is_dir($path)) {
                break;
            }
            $this->hierarchy[] = $current;

            $manifestFile = $path.'/theme.json';
            if (!is_file($manifestFile)) {
                break;
            }
            $decoded = json_decode((string)@file_get_contents($manifestFile), true);
            $current = is_array($decoded) ? (string)($decoded['parent'] ?? '') : '';
        }
    }
}
