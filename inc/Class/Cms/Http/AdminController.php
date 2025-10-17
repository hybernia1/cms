<?php
declare(strict_types=1);

namespace Cms\Http;

use Cms\Utils\AdminNavigation;
use Cms\View\ViewEngine;
use Cms\Http\Admin\CommentsController;
use Cms\Http\Admin\MediaController;
use Cms\Http\Admin\MigrationsController;
use Cms\Http\Admin\NavigationController;
use Cms\Http\Admin\PostsController;
use Cms\Http\Admin\SettingsController;
use Cms\Http\Admin\TermsController;
use Cms\Http\Admin\ThemesController;
use Cms\Http\Admin\UsersController;

final class AdminController
{
    private ViewEngine $view;
    private string $baseViewsPath;

    /** @var array<string,class-string> */
    private const ROUTE_MAP = [
        'posts'      => PostsController::class,
        'media'      => MediaController::class,
        'terms'      => TermsController::class,
        'comments'   => CommentsController::class,
        'themes'     => ThemesController::class,
        'navigation' => NavigationController::class,
        'settings'   => SettingsController::class,
        'migrations' => MigrationsController::class,
        'users'      => UsersController::class,
    ];

    public function __construct(string $baseViewsPath)
    {
        $this->baseViewsPath = $baseViewsPath;
        $this->view = new ViewEngine($baseViewsPath);
    }

public function handle(string $route, string $action): void
{
    if (isset(self::ROUTE_MAP[$route])) {
        $class = self::ROUTE_MAP[$route];
        $controller = new $class($this->baseViewsPath);
        $controller->handle($action);
        return;
    }

    if ($route === 'dashboard') {
        $this->dashboardIndex();
        return;
    }

    $this->dashboardIndex();
}

    private function dashboardIndex(): void
    {
        $data = [
            'pageTitle'   => 'Dashboard',
            'nav'         => AdminNavigation::build('dashboard'),
            'currentUser' => (new \Cms\Auth\AuthService())->user(),
            'flash'       => $this->takeFlash(),
        ];
        $this->view->render('dashboard/index', $data);
    }

    private function takeFlash(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $f = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);
        return is_array($f) ? $f : null;
    }
}
