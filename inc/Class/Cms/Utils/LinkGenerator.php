<?php
declare(strict_types=1);

namespace Cms\Utils;

use Cms\Settings\CmsSettings;

final class LinkGenerator
{
    private bool $pretty;
    private CmsSettings $settings;
    private string $postBase;
    private string $pageBase;
    private string $categoryBase;
    private string $tagBase;

    public function __construct(?bool $pretty = null, ?CmsSettings $settings = null)
    {
        $this->settings = $settings ?? new CmsSettings();
        $bases = $this->settings->permalinkBases();
        $this->postBase = $bases['post_base'];
        $this->pageBase = $bases['page_base'];
        $this->categoryBase = $bases['category_base'];
        $this->tagBase = $bases['tag_base'];
        $this->pretty = $this->resolvePretty($pretty);
    }

    private function resolvePretty(?bool $explicit): bool
    {
        if ($explicit !== null) {
            return $explicit;
        }

        if (!$this->settings->seoUrlsEnabled()) {
            return false;
        }

        return $this->detectPretty();
    }

    private function detectPretty(): bool
    {
        if (defined('BASE_DIR') && is_file(BASE_DIR . '/.htaccess')) {
            return true;
        }
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
        if ($path === '') {
            return false;
        }
        return !preg_match('~\.php($|/)~i', $path);
    }

    private function basePath(): string
    {
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        return $base === '/' ? '' : $base;
    }

    private function prettyPath(string $path): string
    {
        $base = $this->basePath();
        $path = ltrim($path, '/');
        if ($path === '') {
            return $base === '' ? '/' : $base . '/';
        }
        return ($base === '' ? '' : $base) . '/' . $path;
    }

    private function fallback(string $route, array $params = []): string
    {
        $query = array_merge(['r' => $route], $params);
        return './index.php?' . http_build_query($query);
    }

    public function home(): string
    {
        if ($this->pretty) {
            return $this->prettyPath('');
        }

        $base = $this->basePath();
        return $base === '' ? './' : $base . '/';
    }

    public function post(string $slug): string
    {
        $encoded = rawurlencode($slug);
        return $this->pretty
            ? $this->prettyPath($this->postBase . '/' . $encoded)
            : $this->fallback('post', ['slug' => $slug]);
    }

    public function page(string $slug): string
    {
        $encoded = rawurlencode($slug);
        return $this->pretty
            ? $this->prettyPath($this->pageBase . '/' . $encoded)
            : $this->fallback('page', ['slug' => $slug]);
    }

    public function type(string $type): string
    {
        $encoded = rawurlencode($type);
        return $this->pretty
            ? $this->prettyPath('type/' . $encoded)
            : $this->fallback('type', ['type' => $type]);
    }

    public function term(string $slug, ?string $type = null): string
    {
        if ($type === 'category') {
            return $this->category($slug);
        }
        if ($type === 'tag') {
            return $this->tag($slug);
        }
        $encoded = rawurlencode($slug);
        return $this->pretty
            ? $this->prettyPath('term/' . $encoded)
            : $this->fallback('term', ['slug' => $slug]);
    }

    public function category(string $slug): string
    {
        $encoded = rawurlencode($slug);
        return $this->pretty
            ? $this->prettyPath($this->categoryBase . '/' . $encoded)
            : $this->fallback('category', ['slug' => $slug]);
    }

    public function tag(string $slug): string
    {
        $encoded = rawurlencode($slug);
        return $this->pretty
            ? $this->prettyPath($this->tagBase . '/' . $encoded)
            : $this->fallback('tag', ['slug' => $slug]);
    }

    public function admin(): string
    {
        return $this->basePath() . '/admin.php';
    }

    public function terms(?string $type = null): string
    {
        if ($type === null || $type === '') {
            return $this->pretty ? $this->prettyPath('terms') : $this->fallback('terms');
        }
        $encoded = rawurlencode($type);
        return $this->pretty
            ? $this->prettyPath('terms/' . $encoded)
            : $this->fallback('terms', ['type' => $type]);
    }

    public function search(?string $query = null): string
    {
        $query = $query === null ? '' : (string)$query;
        if ($this->pretty) {
            $base = $this->prettyPath('search');
            if ($query === '') {
                return $base;
            }
            return $base . '?' . http_build_query(['s' => $query]);
        }
        $params = $query === '' ? [] : ['s' => $query];
        return $this->fallback('search', $params);
    }

    public function login(): string
    {
        return $this->pretty ? $this->prettyPath('login') : $this->fallback('login');
    }

    public function logout(): string
    {
        return $this->pretty ? $this->prettyPath('logout') : $this->fallback('logout');
    }

    public function register(): string
    {
        return $this->pretty ? $this->prettyPath('register') : $this->fallback('register');
    }

    public function lost(): string
    {
        return $this->pretty ? $this->prettyPath('lost') : $this->fallback('lost');
    }

    public function reset(): string
    {
        return $this->pretty ? $this->prettyPath('reset') : $this->fallback('reset');
    }

    public function commentAction(): string
    {
        return $this->pretty ? $this->prettyPath('comment') : $this->fallback('comment');
    }
}
