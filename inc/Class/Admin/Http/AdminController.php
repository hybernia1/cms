<?php
declare(strict_types=1);

namespace Cms\Admin\Http;

use Cms\Admin\Auth\AuthService;
use Cms\Admin\Domain\Services\PostsService;
use Cms\Admin\Domain\Services\QuickDraftService;
use Cms\Admin\Http\Controllers\CommentsController;
use Cms\Admin\Http\Controllers\MediaController;
use Cms\Admin\Http\Controllers\MigrationsController;
use Cms\Admin\Http\Controllers\NavigationController;
use Cms\Admin\Http\Controllers\PluginsController;
use Cms\Admin\Http\Controllers\PostsController;
use Cms\Admin\Http\Controllers\ProductsController;
use Cms\Admin\Http\Controllers\CategoriesController;
use Cms\Admin\Http\Controllers\StockController;
use Cms\Admin\Http\Controllers\OrdersController;
use Cms\Admin\Http\Controllers\SettingsController;
use Cms\Admin\Http\Controllers\TermsController;
use Cms\Admin\Http\Controllers\ThemesController;
use Cms\Admin\Http\Controllers\WidgetsController;
use Cms\Admin\Http\Controllers\UsersController;
use Cms\Admin\Http\Support\ControllerHelpers;
use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Utils\AdminNavigation;
use Cms\Admin\Utils\DateTimeFactory;
use Cms\Admin\View\ViewEngine;
use Core\Database\SchemaChecker;

final class AdminController
{
    private ViewEngine $view;
    private string $baseViewsPath;
    private AuthService $auth;
    private SchemaChecker $schemaChecker;
    private QuickDraftService $quickDraftService;

    /** @var array<string,class-string> */
    private const ROUTE_MAP = [
        'posts'      => PostsController::class,
        'products'   => \Cms\Admin\Http\Controllers\ProductsController::class,
        'categories' => \Cms\Admin\Http\Controllers\CategoriesController::class,
        'stock'      => \Cms\Admin\Http\Controllers\StockController::class,
        'orders'     => \Cms\Admin\Http\Controllers\OrdersController::class,
        'media'      => MediaController::class,
        'terms'      => TermsController::class,
        'comments'   => CommentsController::class,
        'themes'     => ThemesController::class,
        'navigation' => NavigationController::class,
        'plugins'    => PluginsController::class,
        'widgets'    => WidgetsController::class,
        'settings'   => SettingsController::class,
        'migrations' => MigrationsController::class,
        'users'      => UsersController::class,
    ];

    public function __construct(string $baseViewsPath, ?QuickDraftService $quickDraftService = null, ?SchemaChecker $schemaChecker = null)
    {
        $this->baseViewsPath = $baseViewsPath;
        $this->view = new ViewEngine($baseViewsPath);
        $this->view->setBasePaths($this->resolveViewPaths($baseViewsPath));
        $this->auth = new AuthService();
        $this->schemaChecker = $schemaChecker ?? new SchemaChecker();
        $this->quickDraftService = $quickDraftService ?? new QuickDraftService(schemaChecker: $this->schemaChecker);
    }

    /**
     * @return array<int,string>
     */
    private function resolveViewPaths(string $baseViewsPath): array
    {
        $paths = [];

        $realBase = realpath($baseViewsPath);
        if ($realBase !== false && is_dir($realBase)) {
            $paths[] = $realBase;
        }

        $adminRoot = realpath(dirname($baseViewsPath));
        if ($adminRoot !== false && is_dir($adminRoot)) {
            $paths[] = $adminRoot;
        }

        return $paths === [] ? [$baseViewsPath] : $paths;
    }

    public function handle(string $route, string $action): void
    {
        if (isset(self::ROUTE_MAP[$route])) {
            $class = self::ROUTE_MAP[$route];
            if ($class === NavigationController::class) {
                $controller = new $class($this->baseViewsPath, $this->schemaChecker);
            } else {
                $controller = new $class($this->baseViewsPath);
            }
            $controller->handle($action);
            return;
        }

        if ($route === 'dashboard') {
            if ($action === 'quick-draft' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->quickDraftService->handleQuickDraft();
                return;
            }
            $this->dashboardIndex();
            return;
        }

        $this->dashboardIndex();
    }

    private function dashboardIndex(): void
    {
        $quickDraftEnabled = $this->schemaChecker->hasTable('posts');

        $data = [
            'pageTitle'   => 'Dashboard',
            'nav'         => AdminNavigation::build('dashboard'),
            'currentUser' => $this->auth->user(),
            'flash'       => $this->takeFlash(),
            'csrf'        => ControllerHelpers::csrfToken(),
            'quickDraftTypes' => $quickDraftEnabled ? $this->quickDraftTypes() : [],
            'quickDraftOld'   => $quickDraftEnabled ? $this->quickDraftService->pullQuickDraftOld() : ['title' => '', 'content' => '', 'type' => 'post'],
            'quickDraftRecent' => $quickDraftEnabled ? $this->recentQuickDrafts() : [],
            'quickDraftEnabled' => $quickDraftEnabled,
        ];
        $this->view->render('dashboard/index', $data);
    }


    /**
     * @return array<int,array{value:string,label:string}>
     */
    private function quickDraftTypes(): array
    {
        return [
            ['value' => 'post', 'label' => 'Příspěvek'],
        ];
    }

    /**
     * @return array<int,array{id:int,title:string,type:string,created_at_display:string}>
     */
    private function recentQuickDrafts(): array
    {
        if (!$this->schemaChecker->hasTable('posts')) {
            return [];
        }

        $service = new PostsService();
        $settings = new CmsSettings();
        $items = [];
        foreach ($service->latestDrafts('post', 5) as $row) {
            $id = isset($row['id']) ? (int)$row['id'] : 0;
            if ($id <= 0) {
                continue;
            }
            $title = trim((string)($row['title'] ?? ''));
            $createdRaw = isset($row['created_at']) ? (string)$row['created_at'] : null;
            $createdAt = $createdRaw !== null ? DateTimeFactory::fromStorage($createdRaw) : null;
            $items[] = [
                'id' => $id,
                'title' => $title,
                'type' => (string)($row['type'] ?? 'post'),
                'created_at_display' => $createdAt ? $settings->formatDateTime($createdAt) : '',
            ];
        }

        return $items;
    }

    private function takeFlash(): ?array
    {
        $f = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);
        return is_array($f) ? $f : null;
    }
}
