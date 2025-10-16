<?php
declare(strict_types=1);

namespace Cms\Utils;

final class LinkGenerator
{
    private bool $pretty;

    public function __construct(?bool $pretty = null)
    {
        $this->pretty = $pretty ?? $this->detectPretty();
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

    private function prettyPath(string $path): string
    {
        return './' . ltrim($path, './');
    }

    private function fallback(string $route, array $params = []): string
    {
        $query = array_merge(['r' => $route], $params);
        return './index.php?' . http_build_query($query);
    }

    public function home(): string
    {
        return './';
    }

    public function post(string $slug): string
    {
        $encoded = rawurlencode($slug);
        return $this->pretty
            ? $this->prettyPath('post/' . $encoded)
            : $this->fallback('post', ['slug' => $slug]);
    }

    public function page(string $slug): string
    {
        $encoded = rawurlencode($slug);
        return $this->pretty
            ? $this->prettyPath('page/' . $encoded)
            : $this->fallback('page', ['slug' => $slug]);
    }

    public function type(string $type): string
    {
        $encoded = rawurlencode($type);
        return $this->pretty
            ? $this->prettyPath('type/' . $encoded)
            : $this->fallback('type', ['type' => $type]);
    }

    public function term(string $slug): string
    {
        $encoded = rawurlencode($slug);
        return $this->pretty
            ? $this->prettyPath('term/' . $encoded)
            : $this->fallback('term', ['slug' => $slug]);
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
