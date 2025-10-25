<?php
declare(strict_types=1);

namespace Cms\Admin\Utils;

use Cms\Admin\Domain\PostTypes\PostTypeRegistry;
use Cms\Admin\Settings\CmsSettings;

final class LinkGenerator
{
    private bool $pretty;
    private CmsSettings $settings;
    private string $postBase;
    private string $pageBase;
    private string $categoryBase;
    private string $tagBase;
    private string $authorBase;
    /**
     * @var array<string,string>
     */
    private array $postTypeSlugs = [];

    public function __construct(?bool $pretty = null, ?CmsSettings $settings = null)
    {
        $this->settings = $settings ?? new CmsSettings();
        $bases = $this->settings->permalinkBases();
        $this->postBase = $bases['post_base'];
        $this->pageBase = $bases['page_base'];
        $this->categoryBase = $bases['category_base'];
        $this->tagBase = $bases['tag_base'];
        $this->authorBase = $bases['author_base'];
        $this->pretty = $this->resolvePretty($pretty);
        $this->postTypeSlugs = $this->loadPostTypeSlugs();
    }

    /**
     * @return array<string,string>
     */
    private function loadPostTypeSlugs(): array
    {
        $registered = PostTypeRegistry::all();
        $slugs = [];
        foreach ($registered as $type => $config) {
            $slug = trim((string)($config['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }
            $slugs[$type] = $slug;
        }

        return $slugs;
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
        $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/');
        $dir = dirname($script);
        $dir = str_replace('\\', '/', $dir);
        $dir = rtrim($dir, '/');

        if ($dir === '' || $dir === '.' || $dir === '/') {
            return '';
        }

        return $dir;
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
        return $this->postOfType('post', $slug);
    }

    /**
     * @return array{pretty:string,fallback:array{route:string,params:array<string,string>}}
     */
    public function postTypeBase(string $type): array
    {
        $normalized = trim($type) !== '' ? trim($type) : 'post';

        if ($normalized === 'page') {
            $pretty = trim($this->pageBase, '/');

            return [
                'pretty' => $pretty,
                'fallback' => [
                    'route' => 'page',
                    'params' => [],
                ],
            ];
        }

        if ($normalized === 'post') {
            $pretty = trim($this->postBase, '/');

            return [
                'pretty' => $pretty,
                'fallback' => [
                    'route' => 'post',
                    'params' => [],
                ],
            ];
        }

        $slug = $this->postTypeSlugs[$normalized] ?? $normalized;
        $slug = trim($slug, '/');
        if ($slug === '') {
            $slug = $normalized;
        }

        return [
            'pretty' => $slug,
            'fallback' => [
                'route' => $slug,
                'params' => ['type' => $normalized],
            ],
        ];
    }

    public function postOfType(string $type, string $slug): string
    {
        $base = $this->postTypeBase($type);
        $encoded = rawurlencode($slug);

        if ($this->pretty) {
            $prettyBase = trim($base['pretty'], '/');
            $path = $prettyBase === '' ? $encoded : $prettyBase . '/' . $encoded;

            return $this->prettyPath($path);
        }

        $params = $base['fallback']['params'];
        $params['slug'] = $slug;

        return $this->fallback($base['fallback']['route'], $params);
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

    public function user(?string $slug = null, ?int $id = null): string
    {
        $slug = $slug !== null ? trim($slug) : '';
        $id = $id !== null && $id > 0 ? $id : null;

        if ($this->pretty) {
            $base = trim($this->authorBase, '/');
            $segment = $slug !== '' ? rawurlencode($slug) : ($id !== null ? (string)$id : '');

            if ($base === '') {
                if ($segment === '') {
                    return $this->prettyPath('');
                }

                return $this->prettyPath($segment);
            }

            $path = $base;
            if ($segment !== '') {
                $path .= '/' . $segment;
            }

            return $this->prettyPath($path);
        }

        $params = [];
        if ($slug !== '') {
            $params['slug'] = $slug;
        }
        if ($id !== null) {
            $params['id'] = (string)$id;
        }

        return $this->fallback('user', $params);
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
        $admin = $this->admin();
        $separator = str_contains($admin, '?') ? '&' : '?';
        return $admin . $separator . 'r=auth/login';
    }

    public function logout(): string
    {
        return $this->pretty ? $this->prettyPath('logout') : $this->fallback('logout');
    }

    public function account(): string
    {
        return $this->pretty ? $this->prettyPath('account') : $this->fallback('account');
    }

    public function register(): string
    {
        return $this->pretty ? $this->prettyPath('register') : $this->fallback('register');
    }

    public function lost(): string
    {
        return $this->pretty ? $this->prettyPath('lost') : $this->fallback('lost');
    }

    public function reset(?string $token = null, ?int $userId = null): string
    {
        $query = [];
        $token = $token !== null ? trim($token) : '';
        if ($token !== '') {
            $query['token'] = $token;
        }
        if ($userId !== null && $userId > 0) {
            $query['id'] = $userId;
        }

        if ($this->pretty) {
            $path = $this->prettyPath('reset');
            if ($query === []) {
                return $path;
            }
            return $path . '?' . http_build_query($query);
        }

        return $this->fallback('reset', $query);
    }

    public function commentAction(): string
    {
        return $this->pretty ? $this->prettyPath('comment') : $this->fallback('comment');
    }

    public function prettyUrlsEnabled(): bool
    {
        return $this->pretty;
    }

    public function absolute(string $path): string
    {
        $trimmed = trim($path);
        $siteUrl = trim($this->settings->siteUrl());

        if ($trimmed === '') {
            return $siteUrl !== '' ? $siteUrl : '';
        }

        if (preg_match('~^https?://~i', $trimmed)) {
            return $trimmed;
        }

        if ($siteUrl === '') {
            return $trimmed;
        }

        if (str_starts_with($trimmed, '/')) {
            $parts = parse_url($siteUrl);
            if (is_array($parts) && isset($parts['scheme'], $parts['host'])) {
                $base = $parts['scheme'] . '://' . $parts['host'];
                if (isset($parts['port'])) {
                    $base .= ':' . $parts['port'];
                }

                return rtrim($base, '/') . $trimmed;
            }
        }

        $normalized = $trimmed;
        if (str_starts_with($normalized, './')) {
            $normalized = substr($normalized, 2);
        }

        return rtrim($siteUrl, '/') . '/' . ltrim($normalized, '/');
    }
}
