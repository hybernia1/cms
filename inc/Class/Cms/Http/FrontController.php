<?php
declare(strict_types=1);

namespace Cms\Http;

use Core\Database\Init as DB;
use Cms\Auth\AuthService;
use Cms\Auth\Authorization;
use Cms\Domain\Repositories\NavigationRepository;
use Cms\Domain\Services\SitemapService;
use Cms\Mail\MailService;
use Cms\Mail\TemplateManager;
use Cms\Settings\CmsSettings;
use Cms\Theming\ThemeManager;
use Cms\Theming\ThemeResolver;
use Cms\Utils\DateTimeFactory;
use Cms\Utils\LinkGenerator;
use Cms\View\Assets;
use Cms\View\ViewEngine;

final class FrontController
{
    private ThemeManager $tm;
    private ThemeResolver $resolver;
    private ViewEngine $view;
    private Assets $assets;
    private CmsSettings $settings;
    private LinkGenerator $urls;
    private SitemapService $sitemaps;
    private TemplateManager $mailTemplates;
    private ?array $frontUser = null;
    /** @var array<int,array<string,mixed>> */
    private array $navigation = [];
    /** @var array<int,array<string,mixed>> */
    private array $routes;

    public function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $this->tm         = new ThemeManager();
        $this->resolver   = new ThemeResolver($this->tm);
        $this->view       = new ViewEngine($this->tm->templateBasePath());
        $this->view->setBasePaths($this->tm->templateBases());
        $this->assets     = new Assets($this->tm);
        $this->settings   = new CmsSettings();
        $this->urls       = new LinkGenerator(null, $this->settings);
        $this->sitemaps   = new SitemapService($this->urls, $this->settings);
        $this->frontUser  = (new AuthService())->user(); // sdílíme admin login i na frontendu
        $this->navigation = (new NavigationRepository())->treeByLocation('primary');
        $this->mailTemplates = new TemplateManager();
        $this->routes     = $this->buildRouteDefinitions();
    }

    public function handle(): void
    {
        // POST endpoint pro komentáře
        if (($_GET['r'] ?? '') === 'comment' || ($this->isPath('/comment') && $_SERVER['REQUEST_METHOD'] === 'POST')) {
            $this->submitComment();
            return;
        }

        $routeKey = (string)($_GET['r'] ?? '');
        $matched = $routeKey !== ''
            ? $this->matchRouteByQuery($routeKey)
            : $this->matchRouteByPath($this->currentPath());

        if ($matched === null) {
            $this->notFound();
            return;
        }

        $handler = $matched['handler'];
        $params  = $matched['params'] ?? [];
        $handler($params);
    }

    private function isPath(string $want): bool
    {
        $current = rtrim($this->currentPath(), '/') ?: '/';
        $target  = rtrim($want, '/') ?: '/';
        return $current === $target;
    }

    private function currentPath(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        if ($base && $base !== '/' && str_starts_with($path, $base)) {
            $trimmed = substr($path, strlen($base));
            $path = $trimmed !== false && $trimmed !== '' ? $trimmed : '/';
        }
        $normalized = '/' . ltrim($path, '/');
        return $normalized === '//' ? '/' : $normalized;
    }

    /**
     * @return array{handler: callable, params: array<string,string>}|null
     */
    private function matchRouteByQuery(string $routeKey): ?array
    {
        foreach ($this->routeDefinitions() as $route) {
            if (($route['query'] ?? null) !== $routeKey) {
                continue;
            }

            $params = $this->extractQueryParams($route);
            if (!$this->validateParams($route, $params)) {
                return ['handler' => function (): void { $this->notFound(); }, 'params' => []];
            }

            return ['handler' => $route['handler'], 'params' => $params];
        }

        return null;
    }

    /**
     * @return array{handler: callable, params: array<string,string>}|null
     */
    private function matchRouteByPath(string $path): ?array
    {
        $segments = $this->segments($path);

        foreach ($this->routeDefinitions() as $route) {
            if (!isset($route['path'])) {
                continue;
            }

            $pattern = $this->parsePathPattern((string)$route['path']);
            $params  = $this->matchPathPattern($pattern, $segments);
            if ($params === null) {
                continue;
            }

            if (!$this->validateParams($route, $params)) {
                return ['handler' => function (): void { $this->notFound(); }, 'params' => []];
            }

            return ['handler' => $route['handler'], 'params' => $params];
        }

        return null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function routeDefinitions(): array
    {
        return $this->routes;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function buildRouteDefinitions(): array
    {
        $requireValue = static fn(string $value): bool => $value !== '';

        $permalinks = $this->settings->permalinkBases();
        $postBase = $permalinks['post_base'];
        $pageBase = $permalinks['page_base'];
        $categoryBase = $permalinks['category_base'];
        $tagBase = $permalinks['tag_base'];

        $routes = [
            [
                'name'    => 'home',
                'query'   => 'home',
                'path'    => '/',
                'handler' => function (array $params = []): void { $this->home(); },
            ],
            [
                'name'         => 'post',
                'query'        => 'post',
                'path'         => '/' . $postBase . '/{slug}',
                'queryParams'  => ['slug'],
                'requirements' => ['slug' => $requireValue],
                'handler'      => function (array $params): void { $this->single('post', $params['slug'] ?? ''); },
            ],
            [
                'name'         => 'page',
                'query'        => 'page',
                'path'         => '/' . $pageBase . '/{slug}',
                'queryParams'  => ['slug'],
                'requirements' => ['slug' => $requireValue],
                'handler'      => function (array $params): void { $this->single('page', $params['slug'] ?? ''); },
            ],
            [
                'name'         => 'type',
                'query'        => 'type',
                'path'         => '/type/{type}',
                'queryParams'  => ['type'],
                'requirements' => ['type' => $requireValue],
                'handler'      => function (array $params): void { $this->archive($params['type'] ?? 'post'); },
            ],
            [
                'name'         => 'term',
                'query'        => 'term',
                'path'         => '/term/{slug}',
                'queryParams'  => ['slug'],
                'requirements' => ['slug' => $requireValue],
                'handler'      => function (array $params): void { $this->archiveByTerm($params['slug'] ?? '', null); },
            ],
            [
                'name'         => 'category',
                'query'        => 'category',
                'path'         => '/' . $categoryBase . '/{slug}',
                'queryParams'  => ['slug'],
                'requirements' => ['slug' => $requireValue],
                'handler'      => function (array $params): void { $this->archiveByTerm($params['slug'] ?? '', 'category'); },
            ],
            [
                'name'         => 'tag',
                'query'        => 'tag',
                'path'         => '/' . $tagBase . '/{slug}',
                'queryParams'  => ['slug'],
                'requirements' => ['slug' => $requireValue],
                'handler'      => function (array $params): void { $this->archiveByTerm($params['slug'] ?? '', 'tag'); },
            ],
            [
                'name'        => 'terms',
                'query'       => 'terms',
                'path'        => '/terms/{type?}',
                'queryParams' => [
                    'type' => ['key' => 'type', 'optional' => true],
                ],
                'handler'     => function (array $params): void { $this->terms($params['type'] ?? ''); },
            ],
            [
                'name'        => 'search',
                'query'       => 'search',
                'path'        => '/search',
                'queryParams' => [
                    's' => ['key' => 's', 'optional' => true],
                ],
                'handler'     => function (array $params): void { $this->search($params['s'] ?? ''); },
            ],
            [
                'name'    => 'register',
                'query'   => 'register',
                'path'    => '/register',
                'handler' => function (array $params = []): void { $this->register(); },
            ],
            [
                'name'    => 'lost',
                'query'   => 'lost',
                'path'    => '/lost',
                'handler' => function (array $params = []): void { $this->lost(); },
            ],
            [
                'name'    => 'reset',
                'query'   => 'reset',
                'path'    => '/reset',
                'handler' => function (array $params = []): void { $this->reset(); },
            ],
            [
                'name'    => 'login',
                'query'   => 'login',
                'path'    => '/login',
                'handler' => function (array $params = []): void { $this->login(); },
            ],
            [
                'name'    => 'logout',
                'query'   => 'logout',
                'path'    => '/logout',
                'handler' => function (array $params = []): void { $this->logout(); },
            ],
        ];

        $indexMeta = $this->sitemaps->indexMetadata();
        $routes[] = [
            'name'    => 'sitemap-index',
            'query'   => $indexMeta['route'],
            'path'    => '/' . ltrim($indexMeta['filename'], '/'),
            'handler' => function (array $params = []): void { $this->sitemapIndex(); },
        ];

        foreach ($this->sitemaps->sections() as $key => $meta) {
            $routes[] = [
                'name'    => 'sitemap-' . $key,
                'query'   => $meta['route'],
                'path'    => '/' . ltrim($meta['filename'], '/'),
                'handler' => function (array $params = []) use ($key): void { $this->sitemapSection($key); },
            ];
        }

        return $routes;
    }

    /**
     * @param array<string,mixed> $route
     * @return array<string,string>
     */
    private function extractQueryParams(array $route): array
    {
        $params = [];
        foreach ($route['queryParams'] ?? [] as $key => $definition) {
            if (is_int($key)) {
                $paramName = (string)$definition;
                $sourceKey = (string)$definition;
                $optional  = false;
            } elseif (is_array($definition)) {
                $paramName = (string)$key;
                $sourceKey = (string)($definition['key'] ?? $key);
                $optional  = (bool)($definition['optional'] ?? false);
            } else {
                $paramName = (string)$key;
                $sourceKey = (string)$definition;
                $optional  = false;
            }

            $value = isset($_GET[$sourceKey]) ? (string)$_GET[$sourceKey] : '';
            if ($value === '' && !$optional) {
                $params[$paramName] = '';
                continue;
            }

            $params[$paramName] = $value;
        }

        return $params;
    }

    /**
     * @param array<int,array<string,mixed>> $pattern
     * @param string[] $segments
     * @return array<string,string>|null
     */
    private function matchPathPattern(array $pattern, array $segments): ?array
    {
        if ($pattern === []) {
            return $segments === [] ? [] : null;
        }

        $required = 0;
        foreach ($pattern as $part) {
            if ($part['type'] === 'literal' || ($part['type'] === 'parameter' && !$part['optional'])) {
                $required++;
            }
        }

        $segmentCount = count($segments);
        if ($segmentCount < $required || $segmentCount > count($pattern)) {
            return null;
        }

        $params = [];
        foreach ($pattern as $index => $part) {
            $segment = $segments[$index] ?? null;

            if ($part['type'] === 'literal') {
                if ($segment !== $part['value']) {
                    return null;
                }
                continue;
            }

            if ($segment === null) {
                if ($part['optional']) {
                    $params[$part['name']] = '';
                    continue;
                }
                return null;
            }

            $params[$part['name']] = $segment;
        }

        foreach ($pattern as $part) {
            if ($part['type'] === 'parameter' && $part['optional'] && !array_key_exists($part['name'], $params)) {
                $params[$part['name']] = '';
            }
        }

        return $params;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function parsePathPattern(string $pattern): array
    {
        $trimmed = trim($pattern);
        if ($trimmed === '' || $trimmed === '/') {
            return [];
        }

        $segments = array_values(array_filter(explode('/', trim($trimmed, '/')), static fn($part) => $part !== ''));
        $result = [];

        foreach ($segments as $segment) {
            if (preg_match('/^\{([a-zA-Z0-9_]+)(\?)?\}$/', $segment, $matches)) {
                $result[] = [
                    'type'     => 'parameter',
                    'name'     => $matches[1],
                    'optional' => ($matches[2] ?? '') === '?',
                ];
                continue;
            }

            $result[] = [
                'type'  => 'literal',
                'value' => $segment,
            ];
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $route
     * @param array<string,string> $params
     */
    private function validateParams(array $route, array $params): bool
    {
        if (!isset($route['requirements'])) {
            return true;
        }

        foreach ($route['requirements'] as $key => $rule) {
            $value = $params[$key] ?? '';
            if ($value === '') {
                return false;
            }

            if (is_callable($rule)) {
                if (!$rule($value)) {
                    return false;
                }
                continue;
            }

            if (!preg_match('#^' . $rule . '$#u', $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return string[]
     */
    private function segments(string $path): array
    {
        $trimmed = trim($path, '/');
        if ($trimmed === '') {
            return [];
        }

        return array_values(array_filter(explode('/', $trimmed), static fn($part) => $part !== ''));
    }

    private function siteTitle(): string { return $this->settings->siteTitle(); }

    private function render(string $templateKind, array $params = [], array $data = []): void
    {
        $base = [
            'assets'      => $this->assets,
            'siteTitle'   => $this->siteTitle(),
            'frontUser'   => $this->frontUser,
            'navigation'  => $this->navigation,
            'urls'        => $this->urls,
        ];
        $this->view->share($base);
        $template = $this->resolver->resolve($templateKind, $params);
        $this->view->renderLayout('layouts/base', $template, $base + $data);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function withTitle(array $data, string $title): array
    {
        $data['pageTitle'] = $title;
        return $data;
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'post' => 'Příspěvky',
            'page' => 'Stránky',
            default => ucfirst($type),
        };
    }

    private function tokenPublic(): string
    {
        if (empty($_SESSION['csrf_public'])) {
            $_SESSION['csrf_public'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf_public'];
    }
    private function assertCsrfPublic(): void
    {
        $in = (string)($_POST['csrf'] ?? '');
        if (empty($_SESSION['csrf_public']) || !hash_equals($_SESSION['csrf_public'], $in)) {
            http_response_code(419);
            echo 'CSRF token invalid';
            exit;
        }
    }

    private function sitemapIndex(): void
    {
        $xml = $this->sitemaps->renderIndex();
        $this->xmlResponse($xml);
    }

    private function sitemapSection(string $key): void
    {
        $xml = $this->sitemaps->renderSection($key);
        if ($xml === null) {
            $this->notFound();
            return;
        }

        $this->xmlResponse($xml);
    }

    private function xmlResponse(string $xml): void
    {
        header('Content-Type: application/xml; charset=UTF-8');
        echo $xml;
    }

    // ---------- actions ----------
    private function home(): void
    {
        $latest = DB::query()
            ->table('posts','p')
            ->select(['p.id','p.title','p.slug','p.created_at'])
            ->where('p.status','=','publish')
            ->orderBy('p.created_at','DESC')
            ->limit(10)
            ->get();

        $this->render('home', [], $this->withTitle(['latestPosts' => $latest], 'Poslední příspěvky'));
    }

    private function single(string $type, string $slug): void
    {
        if ($slug === '') { $this->notFound(); return; }

        $row = DB::query()->table('posts','p')->select(['*'])
            ->where('p.type','=', $type)
            ->where('p.slug','=', $slug)
            ->where('p.status','=','publish')
            ->first();

        if (!$row) { $this->notFound(); return; }

        $commentsAllowed = (int)($row['comments_allowed'] ?? 1) === 1 && $type === 'post';
        $tree = [];
        if ($commentsAllowed) {
            $tree = $this->commentsTree((int)$row['id']);
        }

        $tpl = $type === 'page' ? 'page' : 'single';

        $termsByType = [];
        if ($type === 'post') {
            $rows = DB::query()
                ->table('post_terms', 'pt')
                ->select(['t.id','t.slug','t.name','t.type'])
                ->join('terms t', 'pt.term_id', '=', 't.id')
                ->where('pt.post_id', '=', (int)$row['id'])
                ->orderBy('t.type')
                ->orderBy('t.name')
                ->get();

            foreach ($rows as $termRow) {
                $tType = (string)($termRow['type'] ?? '');
                if ($tType === '') {
                    continue;
                }
                $termsByType[$tType][] = $termRow;
            }
        }

        $payload = [
            $tpl === 'page' ? 'page' : 'post' => $row,
            'commentsTree'    => $tree,
            'commentsAllowed' => $commentsAllowed,
            'csrfPublic'      => $this->tokenPublic(),
            'commentFlash'    => $this->readFrontFlash(),
            'frontUser'       => $this->frontUser,
            'termsByType'     => $termsByType,
        ];
        $title = trim((string)($row['title'] ?? ''));
        if ($title === '') {
            $title = $type === 'page' ? 'Stránka' : 'Příspěvek';
        }

        $this->render($tpl, ['type' => $type], $this->withTitle($payload, $title));
    }

    private function archive(string $type): void
    {
        $items = DB::query()
            ->table('posts','p')
            ->select(['p.id','p.title','p.slug','p.created_at'])
            ->where('p.type','=', $type)
            ->where('p.status','=','publish')
            ->orderBy('p.created_at','DESC')
            ->limit(50)
            ->get();

        $title = 'Archiv – ' . $this->typeLabel($type);
        $this->render('archive', ['type' => $type], $this->withTitle([
            'items' => $items,
            'type'  => $type,
            'term'  => null,
        ], $title));
    }

    private function archiveByTerm(string $termSlug, ?string $type): void
    {
        if ($termSlug === '') { $this->notFound(); return; }

        $termQuery = DB::query()
            ->table('terms')
            ->select(['id','name','slug','type'])
            ->where('slug','=', $termSlug);

        if ($type !== null) {
            $termQuery->where('type','=', $type);
        }

        $term = $termQuery->first();
        if (!$term) { $this->notFound(); return; }

        $items = DB::query()
            ->table('post_terms','pt')
            ->select(['p.id','p.title','p.slug','p.created_at'])
            ->join('posts p','pt.post_id','=','p.id')
            ->where('pt.term_id','=', (int)$term['id'])
            ->where('p.status','=','publish')
            ->orderBy('p.created_at','DESC')
            ->limit(50)
            ->get();

        $typeLabel = match ((string)($term['type'] ?? '')) {
            'category' => 'Kategorie',
            'tag'      => 'Štítek',
            default    => ucfirst((string)($term['type'] ?? 'Term')),
        };

        $label = $typeLabel . ': ' . (string)$term['name'];
        $this->render('archive', [], $this->withTitle([
            'items' => $items,
            'type'  => $label,
            'term'  => $term,
        ], 'Archiv – ' . $label));
    }

    private function search(string $q): void
    {
        $q = trim($q);
        $items = [];
        if ($q !== '') {
            $like = '%' . $q . '%';
            $items = DB::query()
                ->table('posts','p')
                ->select(['p.id','p.title','p.slug','p.created_at','p.type'])
                ->where('p.status','=','publish')
                ->whereLike('p.title', $like)
                ->orderBy('p.created_at','DESC')
                ->limit(50)
                ->get();
        }

        $title = $q === '' ? 'Hledání' : 'Hledání: ' . $q;
        $this->render('search', [], $this->withTitle(['items' => $items, 'query' => $q], $title));
    }

    private function terms(string $type): void
    {
        $type = trim($type);

        $typeRows = DB::query()
            ->table('terms')
            ->select(["DISTINCT type AS type"])
            ->orderBy('type')
            ->get();

        $availableTypes = array_map(static fn(array $row): string => (string)$row['type'], $typeRows);
        if ($type !== '' && !in_array($type, $availableTypes, true)) {
            $type = '';
        }

        $query = DB::query()
            ->table('terms', 't')
            ->select([
                't.id',
                't.slug',
                't.name',
                't.type',
                't.description',
                't.created_at',
                "SUM(CASE WHEN p.status = 'publish' THEN 1 ELSE 0 END) AS posts_count",
            ])
            ->leftJoin('post_terms pt', 't.id', '=', 'pt.term_id')
            ->leftJoin('posts p', 'pt.post_id', '=', 'p.id')
            ->groupBy(['t.id','t.slug','t.name','t.type','t.description','t.created_at'])
            ->orderBy('t.type')
            ->orderBy('t.name');

        if ($type !== '') {
            $query->where('t.type', '=', $type);
        }

        $terms = $query->get();

        $title = 'Termy';
        if ($type !== '') {
            $title .= ' – ' . ucfirst($type);
        }

        $this->render('terms', [], $this->withTitle([
            'terms'          => $terms,
            'activeType'     => $type !== '' ? $type : null,
            'availableTypes' => $availableTypes,
        ], $title));
    }

    private function notFound(): void
    {
        http_response_code(404);
        $this->render('404', [], ['pageTitle' => 'Stránka nenalezena']);
    }

    // ---------- comments helpers ----------
    private function commentsTree(int $postId): array
    {
        $rows = DB::query()->table('comments','c')
            ->select(['c.id','c.post_id','c.user_id','c.author_name','c.author_email','c.content','c.status','c.parent_id','c.created_at'])
            ->where('c.post_id','=', $postId)
            ->where('c.status','=','published')
            ->orderBy('c.created_at','ASC')
            ->get();

        $byId = [];
        foreach ($rows as $r) {
            $r['children'] = [];
            $byId[(int)$r['id']] = $r;
        }

        $root = [];
        $rootCache = [];
        $findRootId = function (int $commentId) use (&$byId, &$rootCache, &$findRootId): int {
            if (isset($rootCache[$commentId])) {
                return $rootCache[$commentId];
            }
            if (!isset($byId[$commentId])) {
                return $rootCache[$commentId] = $commentId;
            }
            $parentId = (int)($byId[$commentId]['parent_id'] ?? 0);
            if ($parentId <= 0 || !isset($byId[$parentId]) || $parentId === $commentId) {
                return $rootCache[$commentId] = $commentId;
            }
            return $rootCache[$commentId] = $findRootId($parentId);
        };

        foreach ($byId as $id => &$node) {
            $threadRootId = $findRootId($id);
            if ($threadRootId === $id) {
                $root[] = &$node;
                continue;
            }
            if (isset($byId[$threadRootId])) {
                $byId[$threadRootId]['children'][] = &$node;
            } else {
                $root[] = &$node;
            }
        }
        unset($node);

        return $root;
    }

    private function writeFrontFlash(string $type, string $msg): void
    {
        $_SESSION['_f_flash'] = ['type'=>$type,'msg'=>$msg];
    }
    private function readFrontFlash(): ?array
    {
        $f = $_SESSION['_f_flash'] ?? null;
        unset($_SESSION['_f_flash']);
        return is_array($f) ? $f : null;
    }

    private function submitComment(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->notFound(); return; }

        try {
            $this->assertCsrfPublic();

            // Honeypot
            if (!empty($_POST['website'])) {
                $this->writeFrontFlash('success','Komentář byl odeslán ke schválení.');
                $this->redirectBack();
                return;
            }

            // Rate-limit 30 s
            $now  = time();
            $last = (int)($_SESSION['_last_comment_ts'] ?? 0);
            if ($now - $last < 30) throw new \RuntimeException('Zkuste to prosím znovu za chvíli.');
            $_SESSION['_last_comment_ts'] = $now;

            $postId = (int)($_POST['post_id'] ?? 0);
            $post   = DB::query()->table('posts')->select(['id','slug','type','comments_allowed','status'])
                ->where('id','=', $postId)->first();
            if (!$post || (string)$post['status'] !== 'publish') throw new \RuntimeException('Příspěvek neexistuje.');
            if ((int)($post['comments_allowed'] ?? 1) !== 1 || (string)$post['type'] !== 'post') {
                throw new \RuntimeException('Komentáře jsou u tohoto příspěvku zakázány.');
            }

            $parentId = (int)($_POST['parent_id'] ?? 0);
            if ($parentId > 0) {
                $parentId = $this->resolveCommentRootId($parentId, $postId);
            }

            // přihlášený uživatel?
            $user = $this->frontUser;
            $isAdmin = Authorization::isAdmin($user);

            if ($user) {
                $authorName  = (string)($user['name'] ?? 'Uživatel');
                $authorEmail = (string)($user['email'] ?? '');
                $userId      = (int)($user['id'] ?? 0);
            } else {
                $authorName  = trim((string)($_POST['author_name'] ?? ''));
                $authorEmail = trim((string)($_POST['author_email'] ?? ''));
                $userId      = 0;
            }

            $text = trim((string)($_POST['content'] ?? ''));
            if ($authorName === '' || $text === '') throw new \RuntimeException('Jméno i text komentáře jsou povinné.');
            if ($authorEmail !== '' && !filter_var($authorEmail, FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('Neplatný e-mail.');

            // INSERT bez ->values(), použijeme insertRow(...)
            DB::query()->table('comments')->insertRow([
                'post_id'    => $postId,
                'user_id'    => $userId,
                'author_name'=> $authorName,
                'author_email'=> $authorEmail,
                'content'    => $text,
                'status'     => $isAdmin ? 'published' : 'draft',
                'parent_id'  => $parentId ?: null,
                'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
                'ua'         => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => DateTimeFactory::nowString(),
            ])->execute();

            $message = $isAdmin ? 'Komentář byl publikován.' : 'Komentář byl odeslán ke schválení.';
            $this->writeFrontFlash('success', $message);
            $this->redirectToPost((string)$post['slug']);
        } catch (\Throwable $e) {
            $this->writeFrontFlash('danger', $e->getMessage());
            $this->redirectBack();
        }
    }

    private function redirectBack(): void
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? './';
        header('Location: ' . $ref);
        exit;
    }
    private function redirectToPost(string $slug): void
    {
        header('Location: ' . $this->urls->post($slug));
        exit;
    }

    private function resolveCommentRootId(int $commentId, int $postId): int
    {
        if ($commentId <= 0) {
            return 0;
        }

        $currentId = $commentId;
        $guard = 0;
        while ($currentId > 0 && $guard < 20) {
            $row = DB::query()->table('comments')->select(['id','parent_id','post_id'])->where('id','=', $currentId)->first();
            if (!$row || (int)($row['post_id'] ?? 0) !== $postId) {
                return 0;
            }
            $parentId = (int)($row['parent_id'] ?? 0);
            if ($parentId <= 0 || $parentId === $currentId) {
                return (int)$row['id'];
            }
            $currentId = $parentId;
            $guard++;
        }

        return 0;
    }

    // ---------- auth ----------
    private function login(): void
    {
        $auth = new \Cms\Auth\AuthService();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->assertCsrfPublic();
            $email = trim((string)($_POST['email'] ?? ''));
            $pass  = (string)($_POST['password'] ?? '');
            if ($email === '' || $pass === '') {
                $this->render('login', [], $this->withTitle([
                    'csrfPublic' => $this->tokenPublic(),
                    'type'       => 'danger',
                    'msg'        => 'Vyplňte e-mail i heslo.',
                ], 'Přihlášení'));
                return;
            }
            if ($auth->attempt($email, $pass)) {
                header('Location: ./'); exit;
            }
            $this->render('login', [], $this->withTitle([
                'csrfPublic' => $this->tokenPublic(),
                'type'       => 'danger',
                'msg'        => 'Nesprávný e-mail nebo heslo.',
            ], 'Přihlášení'));
            return;
        }

        $this->render('login', [], $this->withTitle([
            'csrfPublic' => $this->tokenPublic(),
        ], 'Přihlášení'));
    }

    private function logout(): void
    {
        (new \Cms\Auth\AuthService())->logout();
        header('Location: ./'); exit;
    }

    private function register(): void
    {
        // Bez volání privátní CmsSettings::row()
        $allow = (int)(DB::query()->table('settings')->select(['allow_registration'])->where('id','=',1)->value('allow_registration') ?? 1);
        if ($allow !== 1) {
            $this->render('register-disabled', [], ['pageTitle' => 'Registrace']);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->assertCsrfPublic();
            $name  = trim((string)($_POST['name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $pass  = (string)($_POST['password'] ?? '');
            if ($name === '' || $email === '' || $pass === '') {
                $this->render('register', [], $this->withTitle([
                    'csrfPublic' => $this->tokenPublic(),
                    'type'       => 'danger',
                    'msg'        => 'Vyplňte všechna pole.',
                ], 'Registrace'));
                return;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->render('register', [], $this->withTitle([
                    'csrfPublic' => $this->tokenPublic(),
                    'type'       => 'danger',
                    'msg'        => 'Neplatný e-mail.',
                ], 'Registrace'));
                return;
            }

            $exists = DB::query()->table('users')->select(['id'])->where('email','=', $email)->first();
            if ($exists) {
                $this->render('register', [], $this->withTitle([
                    'csrfPublic' => $this->tokenPublic(),
                    'type'       => 'danger',
                    'msg'        => 'Účet s tímto e-mailem už existuje.',
                ], 'Registrace'));
                return;
            }

            $hash = password_hash($pass, PASSWORD_DEFAULT);
            DB::query()->table('users')->insertRow([
                'name'          => $name,
                'email'         => $email,
                'password_hash' => $hash,
                'role'          => 'user',
                'active'        => 1,
                'token'         => null,
                'token_expire'  => null,
                'created_at'    => DateTimeFactory::nowString(),
                'updated_at'    => DateTimeFactory::nowString(),
            ])->execute();

            $this->render('register-success', [], $this->withTitle([
                'email' => $email,
            ], 'Registrace dokončena'));
            return;
        }
        $this->render('register', [], $this->withTitle([
            'csrfPublic' => $this->tokenPublic(),
        ], 'Registrace'));
    }

    private function lost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->assertCsrfPublic();
            $email = trim((string)($_POST['email'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->render('lost', [], $this->withTitle([
                    'csrfPublic' => $this->tokenPublic(),
                    'type'       => 'danger',
                    'msg'        => 'Zadejte platný e-mail.',
                ], 'Zapomenuté heslo'));
                return;
            }
            $user = DB::query()->table('users')->select(['id','name','email','active'])->where('email','=', $email)->first();

            // vždy zobraz pozitivní odpověď kvůli bezpečnosti
            if (!$user || (int)$user['active'] !== 1) {
                $this->render('lost-done', [], ['pageTitle' => 'Zapomenuté heslo']);
                return;
            }

            $token = bin2hex(random_bytes(20));
            $exp   = DateTimeFactory::now()->modify('+1 hour')->format('Y-m-d H:i:s'); // 1 hod
            DB::query()->table('users')->update([
                'token'        => $token,
                'token_expire' => $exp,
                'updated_at'   => DateTimeFactory::nowString(),
            ])->where('id','=', (int)$user['id'])->execute();

            // email
            $cs = $this->settings;
            $site = $cs->siteTitle();
            $base = (string)(DB::query()->table('settings')->select(['site_url'])->where('id','=',1)->value('site_url') ?? '');
            $resetUrl = rtrim($base, '/') . '/reset?token=' . urlencode($token);

            $template = $this->mailTemplates->render('lost_password', [
                'resetUrl' => $resetUrl,
                'siteTitle' => $site,
                'userName' => (string)($user['name'] ?? ''),
            ]);

            (new MailService($cs))
                ->sendTemplate((string)$user['email'], $template, (string)($user['name'] ?? ''));

            $this->render('lost-done', [], ['pageTitle' => 'Zapomenuté heslo']);
            return;
        }
        $this->render('lost', [], $this->withTitle([
            'csrfPublic' => $this->tokenPublic(),
        ], 'Zapomenuté heslo'));
    }

    private function reset(): void
    {
        $token = (string)($_GET['token'] ?? ($_POST['token'] ?? ''));
        if ($token === '') { $this->render('reset-invalid', [], ['pageTitle' => 'Obnova hesla']); return; }

        $user = DB::query()->table('users')
            ->select(['id','token','token_expire','email','name'])
            ->where('token','=', $token)
            ->first();

        if (!$user || ($user['token_expire'] && strtotime((string)$user['token_expire']) < time())) {
            $this->render('reset-invalid', [], ['pageTitle' => 'Obnova hesla']); return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->assertCsrfPublic();
            $p1 = (string)($_POST['password'] ?? '');
            $p2 = (string)($_POST['password2'] ?? '');
            if ($p1 === '' || $p1 !== $p2) {
                $this->render('reset', [], $this->withTitle([
                    'csrfPublic' => $this->tokenPublic(),
                    'token'      => $token,
                    'type'       => 'danger',
                    'msg'        => 'Hesla se neshodují.',
                ], 'Obnova hesla'));
                return;
            }
            $hash = password_hash($p1, PASSWORD_DEFAULT);
            DB::query()->table('users')->update([
                'password_hash' => $hash,
                'token'         => null,
                'token_expire'  => null,
                'updated_at'    => DateTimeFactory::nowString(),
            ])->where('id','=', (int)$user['id'])->execute();

            $this->render('reset-done', [], ['pageTitle' => 'Obnova hesla']);
            return;
        }

        $this->render('reset', [], $this->withTitle([
            'csrfPublic' => $this->tokenPublic(),
            'token'      => $token,
        ], 'Obnova hesla'));
    }
}