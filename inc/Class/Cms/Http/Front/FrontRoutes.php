<?php
declare(strict_types=1);

namespace Cms\Http\Front;

use Cms\Domain\Services\CommentTreeService;

final class FrontRoutes
{
    private HomeController $home;
    private SingleController $single;
    private ArchiveController $archive;
    private TermsController $terms;
    private SearchController $search;
    private AuthController $auth;
    private CommentsController $comments;
    private SitemapController $sitemaps;

    public function __construct(private readonly FrontServiceContainer $services)
    {
        $commentTree = new CommentTreeService();

        $this->home = new HomeController($services);
        $this->single = new SingleController($services, $commentTree);
        $this->archive = new ArchiveController($services);
        $this->terms = new TermsController($services);
        $this->search = new SearchController($services);
        $this->auth = new AuthController($services);
        $this->comments = new CommentsController($services, treeService: $commentTree);
        $this->sitemaps = new SitemapController($services);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function all(): array
    {
        $requireValue = static fn(string $value): bool => $value !== '';

        $permalinks = $this->services->settings()->permalinkBases();
        $postBase = $permalinks['post_base'];
        $pageBase = $permalinks['page_base'];
        $categoryBase = $permalinks['category_base'];
        $tagBase = $permalinks['tag_base'];

        $routes = [
            [
                'name'    => 'home',
                'query'   => 'home',
                'path'    => '/',
                'handler' => function (array $params = []): void { ($this->home)(); },
            ],
            [
                'name'         => 'post',
                'query'        => 'post',
                'path'         => '/' . $postBase . '/{slug}',
                'queryParams'  => ['slug'],
                'requirements' => ['slug' => $requireValue],
                'handler'      => function (array $params): void { $this->single->post($params['slug'] ?? ''); },
            ],
            [
                'name'         => 'page',
                'query'        => 'page',
                'path'         => '/' . $pageBase . '/{slug}',
                'queryParams'  => ['slug'],
                'requirements' => ['slug' => $requireValue],
                'handler'      => function (array $params): void { $this->single->page($params['slug'] ?? ''); },
            ],
            [
                'name'         => 'type',
                'query'        => 'type',
                'path'         => '/type/{type}',
                'queryParams'  => ['type'],
                'requirements' => ['type' => $requireValue],
                'handler'      => function (array $params): void { $this->archive->byType($params['type'] ?? 'post'); },
            ],
            [
                'name'         => 'term',
                'query'        => 'term',
                'path'         => '/term/{slug}',
                'queryParams'  => ['slug'],
                'requirements' => ['slug' => $requireValue],
                'handler'      => function (array $params): void { $this->archive->byTerm($params['slug'] ?? '', null); },
            ],
            [
                'name'         => 'category',
                'query'        => 'category',
                'path'         => '/' . $categoryBase . '/{slug}',
                'queryParams'  => ['slug'],
                'requirements' => ['slug' => $requireValue],
                'handler'      => function (array $params): void { $this->archive->byTerm($params['slug'] ?? '', 'category'); },
            ],
            [
                'name'         => 'tag',
                'query'        => 'tag',
                'path'         => '/' . $tagBase . '/{slug}',
                'queryParams'  => ['slug'],
                'requirements' => ['slug' => $requireValue],
                'handler'      => function (array $params): void { $this->archive->byTerm($params['slug'] ?? '', 'tag'); },
            ],
            [
                'name'        => 'terms',
                'query'       => 'terms',
                'path'        => '/terms/{type?}',
                'queryParams' => [
                    'type' => ['key' => 'type', 'optional' => true],
                ],
                'handler'     => function (array $params): void { ($this->terms)($params['type'] ?? ''); },
            ],
            [
                'name'        => 'search',
                'query'       => 'search',
                'path'        => '/search',
                'queryParams' => [
                    's' => ['key' => 's', 'optional' => true],
                ],
                'handler'     => function (array $params): void { ($this->search)($params['s'] ?? ''); },
            ],
            [
                'name'    => 'register',
                'query'   => 'register',
                'path'    => '/register',
                'handler' => function (array $params = []): void { $this->auth->register(); },
            ],
            [
                'name'    => 'lost',
                'query'   => 'lost',
                'path'    => '/lost',
                'handler' => function (array $params = []): void { $this->auth->lost(); },
            ],
            [
                'name'    => 'reset',
                'query'   => 'reset',
                'path'    => '/reset',
                'handler' => function (array $params = []): void { $this->auth->reset(); },
            ],
            [
                'name'    => 'login',
                'query'   => 'login',
                'path'    => '/login',
                'handler' => function (array $params = []): void { $this->auth->login(); },
            ],
            [
                'name'    => 'logout',
                'query'   => 'logout',
                'path'    => '/logout',
                'handler' => function (array $params = []): void { $this->auth->logout(); },
            ],
            [
                'name'    => 'comment',
                'query'   => 'comment',
                'path'    => '/comment',
                'handler' => function (array $params = []): void { $this->comments->submit(); },
            ],
        ];

        $indexMeta = $this->services->sitemaps()->indexMetadata();
        $routes[] = [
            'name'    => 'sitemap-index',
            'query'   => $indexMeta['route'],
            'path'    => '/' . ltrim($indexMeta['filename'], '/'),
            'handler' => function (array $params = []): void { $this->sitemaps->index(); },
        ];

        foreach ($this->services->sitemaps()->sections() as $key => $meta) {
            $routes[] = [
                'name'    => 'sitemap-' . $key,
                'query'   => $meta['route'],
                'path'    => '/' . ltrim($meta['filename'], '/'),
                'handler' => function (array $params = []) use ($key): void { $this->sitemaps->section($key); },
            ];
        }

        return $routes;
    }
}
