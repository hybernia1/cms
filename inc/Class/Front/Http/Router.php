<?php
declare(strict_types=1);

namespace Cms\Front\Http;

use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Utils\LinkGenerator;
use Cms\Front\Data\MenuProvider;
use Cms\Front\Data\PostProvider;
use Cms\Front\Data\TermProvider;
use Cms\Front\Support\SeoMeta;
use Cms\Front\View\ThemeViewEngine;

final class Router
{
    private ThemeViewEngine $view;
    private PostProvider $posts;
    private TermProvider $terms;
    private MenuProvider $menus;
    private CmsSettings $settings;
    private LinkGenerator $links;

    public function __construct(
        ThemeViewEngine $view,
        PostProvider $posts,
        TermProvider $terms,
        MenuProvider $menus,
        ?CmsSettings $settings = null,
        ?LinkGenerator $links = null
    ) {
        $this->view = $view;
        $this->posts = $posts;
        $this->terms = $terms;
        $this->menus = $menus;
        $this->settings = $settings ?? new CmsSettings();
        $this->links = $links ?? new LinkGenerator();

        $this->view->share([
            'navigation' => $this->menus->menusByLocation(),
        ]);
    }

    public function dispatch(): void
    {
        $result = $this->resolve();
        http_response_code($result->status);
        $this->view->renderWithLayout($result->layout, $result->template, $result->data);
    }

    private function resolve(): RouteResult
    {
        $route = $this->detectRoute();
        $name = $route['name'];
        $params = $route['params'];

        return match ($name) {
            'home' => $this->handleHome(),
            'post' => $this->handlePost((string)($params['slug'] ?? '')),
            'page' => $this->handlePage((string)($params['slug'] ?? '')),
            'type' => $this->handleType((string)($params['type'] ?? 'post')),
            'category' => $this->handleTerm((string)($params['slug'] ?? ''), 'category'),
            'tag' => $this->handleTerm((string)($params['slug'] ?? ''), 'tag'),
            'search' => $this->handleSearch((string)($params['query'] ?? ($params['s'] ?? ''))),
            default => $this->notFound(),
        };
    }

    /**
     * @return array{name:string,params:array<string,mixed>}
     */
    private function detectRoute(): array
    {
        $route = isset($_GET['r']) ? (string)$_GET['r'] : null;
        if ($route !== null && $route !== '') {
            $params = $_GET;
            unset($params['r']);
            return ['name' => $route, 'params' => $params];
        }

        if (isset($_GET['s'])) {
            return ['name' => 'search', 'params' => ['query' => (string)$_GET['s']]];
        }

        if (!$this->links->prettyUrlsEnabled()) {
            return ['name' => 'home', 'params' => []];
        }

        return $this->detectPrettyRoute();
    }

    /**
     * @return array{name:string,params:array<string,mixed>}
     */
    private function detectPrettyRoute(): array
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
        $path = $this->trimBase($path);
        if ($path === '' || $path === 'index.php') {
            return ['name' => 'home', 'params' => []];
        }

        $segments = array_values(array_filter(explode('/', $path), static fn ($part) => $part !== ''));
        $bases = $this->settings->permalinkBases();

        if ($segments !== []) {
            $first = $segments[0];
            $second = $segments[1] ?? '';

            if ($bases['post_base'] !== '' && $first === trim($bases['post_base'], '/')) {
                return ['name' => 'post', 'params' => ['slug' => $second]];
            }
            if ($bases['page_base'] !== '' && $first === trim($bases['page_base'], '/')) {
                return ['name' => 'page', 'params' => ['slug' => $second]];
            }
            if ($bases['category_base'] !== '' && $first === trim($bases['category_base'], '/')) {
                return ['name' => 'category', 'params' => ['slug' => $second]];
            }
            if ($bases['tag_base'] !== '' && $first === trim($bases['tag_base'], '/')) {
                return ['name' => 'tag', 'params' => ['slug' => $second]];
            }
            if ($first === 'type') {
                return ['name' => 'type', 'params' => ['type' => $second]];
            }
            if ($first === 'search') {
                $query = $_GET['s'] ?? ($_GET['q'] ?? '');
                return ['name' => 'search', 'params' => ['query' => (string)$query]];
            }
        }

        // Pokud je page base prázdný, považuj první segment za slug stránky.
        if ($bases['page_base'] === '' && $segments !== []) {
            return ['name' => 'page', 'params' => ['slug' => $segments[0]]];
        }

        // Pokud je post base prázdný, zkus nejdříve post a případně fallback na stránku.
        if ($bases['post_base'] === '' && $segments !== []) {
            $slug = $segments[0];
            $post = $this->posts->findPublished($slug, 'post');
            if ($post) {
                return ['name' => 'post', 'params' => ['slug' => $slug]];
            }
        }

        return ['name' => 'page', 'params' => ['slug' => $segments[0] ?? '']];
    }

    private function trimBase(string $path): string
    {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $base = rtrim(str_replace('\\', '/', (string)dirname($script)), '/');
        if ($base !== '' && $base !== '.') {
            $prefix = '/' . ltrim($base, '/');
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
            }
        }
        return trim($path, '/');
    }

    private function handleHome(): RouteResult
    {
        $posts = $this->posts->latest('post', 10);
        $meta = new SeoMeta($this->settings->siteTitle(), canonical: $this->links->home());

        return new RouteResult('home', [
            'posts' => $posts,
            'meta' => $meta->toArray(),
        ]);
    }

    private function handlePost(string $slug): RouteResult
    {
        if ($slug === '') {
            return $this->notFound();
        }

        $post = $this->posts->findPublished($slug, 'post');
        if (!$post) {
            return $this->notFound();
        }

        $meta = new SeoMeta(
            $post['title'] . ' | ' . $this->settings->siteTitle(),
            $post['excerpt'],
            $post['permalink']
        );

        return new RouteResult('single', [
            'post' => $post,
            'meta' => $meta->toArray(),
        ]);
    }

    private function handlePage(string $slug): RouteResult
    {
        if ($slug === '') {
            return $this->notFound();
        }

        $page = $this->posts->findPublished($slug, 'page');
        if (!$page) {
            return $this->notFound();
        }

        $meta = new SeoMeta(
            $page['title'] . ' | ' . $this->settings->siteTitle(),
            $page['excerpt'],
            $page['permalink']
        );

        return new RouteResult('page', [
            'page' => $page,
            'meta' => $meta->toArray(),
        ]);
    }

    private function handleType(string $type): RouteResult
    {
        $type = $type !== '' ? $type : 'post';
        $posts = $this->posts->latest($type, 20);
        $meta = new SeoMeta(
            ucfirst($type) . ' | ' . $this->settings->siteTitle(),
            canonical: $this->links->type($type)
        );

        return new RouteResult('archive', [
            'posts' => $posts,
            'type' => $type,
            'meta' => $meta->toArray(),
        ]);
    }

    private function handleTerm(string $slug, string $type): RouteResult
    {
        if ($slug === '') {
            return $this->notFound();
        }

        $term = $this->terms->findBySlug($slug, $type);
        if (!$term) {
            return $this->notFound();
        }

        $posts = $this->posts->forTerm($slug, $type, 20);

        $canonical = $type === 'category'
            ? $this->links->category($slug)
            : $this->links->tag($slug);

        $meta = new SeoMeta(
            (string)$term['name'] . ' | ' . $this->settings->siteTitle(),
            canonical: $canonical
        );

        $template = $type === 'category' ? 'category' : 'tag';

        return new RouteResult($template, [
            'term' => $term,
            'posts' => $posts,
            'meta' => $meta->toArray(),
        ]);
    }

    private function handleSearch(string $query): RouteResult
    {
        $query = trim($query);
        $posts = $query === '' ? [] : $this->posts->search($query, 20);

        $canonical = $this->links->search($query !== '' ? $query : null);
        $meta = new SeoMeta(
            ($query === '' ? 'Vyhledávání' : 'Hledám "' . $query . '"') . ' | ' . $this->settings->siteTitle(),
            canonical: $canonical
        );

        return new RouteResult('search', [
            'query' => $query,
            'posts' => $posts,
            'meta' => $meta->toArray(),
        ]);
    }

    private function notFound(): RouteResult
    {
        $meta = new SeoMeta('Nenalezeno | ' . $this->settings->siteTitle(), canonical: null);

        return new RouteResult('404', [
            'meta' => $meta->toArray(),
        ], 404);
    }
}
