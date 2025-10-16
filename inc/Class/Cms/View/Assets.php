<?php
declare(strict_types=1);

namespace Cms\View;

use Cms\Theming\ThemeManager;

final class Assets
{
    public function __construct(
        private readonly ThemeManager $tm = new ThemeManager(),
        private readonly string $publicBaseUrl = ''
    ) {}

    private function themePath(): string { return $this->tm->activePath(); }
    /** @return array<int,array{slug:string,path:string}> */
    private function themeRoots(): array { return $this->tm->themeRoots(); }

    private function themeUrl(string $slug = null): string
    {
        // zjisti base URL k /themes z aktuálního requestu
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $baseScript = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        $slug = $slug ?? $this->tm->activeSlug();
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($baseScript ? $baseScript : '') . '/themes/' . $slug;
    }

    private function globalUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $baseScript = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($baseScript ? $baseScript : '') . '/assets';
    }

    public function url(string $path): string
    {
        $path = ltrim($path, '/');
        foreach ($this->themeRoots() as $root) {
            $themeFile = rtrim($root['path'], '/').'/'.$path;
            if (is_file($themeFile)) {
                $ver = (string)@filemtime($themeFile);
                return $this->themeUrl($root['slug']) . '/' . $path . ($ver ? '?v='.$ver : '');
            }
        }
        // fallback na /assets
        $globalFile = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\') . '/assets/' . $path;
        $ver = is_file($globalFile) ? (string)@filemtime($globalFile) : '';
        return $this->globalUrl() . '/' . $path . ($ver ? '?v='.$ver : '');
    }

    /** @param string|array<int,string> $files */
    public function css(string|array $files): string
    {
        $out = '';
        foreach ((array)$files as $f) {
            $out .= '<link rel="stylesheet" href="'.htmlspecialchars($this->url($f), ENT_QUOTES, 'UTF-8').'">' . "\n";
        }
        return $out;
    }

    /** @param string|array<int,string> $files */
    public function js(string|array $files, bool $defer = true): string
    {
        $out = '';
        foreach ((array)$files as $f) {
            $deferAttr = $defer ? ' defer' : '';
            $out .= '<script src="'.htmlspecialchars($this->url($f), ENT_QUOTES, 'UTF-8').'"'.$deferAttr.'></script>' . "\n";
        }
        return $out;
    }
}
