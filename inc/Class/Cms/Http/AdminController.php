<?php
declare(strict_types=1);

namespace Cms\Http;

use Cms\View\ViewEngine;

final class AdminController
{
    private ViewEngine $view;
    private string $baseViewsPath;

    public function __construct(string $baseViewsPath)
    {
        $this->baseViewsPath = $baseViewsPath;
        $this->view = new ViewEngine($baseViewsPath);
    }

public function handle(string $route, string $action): void
{
    switch ($route) {
        case 'posts':       (new \Cms\Http\Admin\PostsController($this->baseViewsPath))->handle($action); return;
        case 'media':       (new \Cms\Http\Admin\MediaController($this->baseViewsPath))->handle($action); return;
        case 'terms':       (new \Cms\Http\Admin\TermsController($this->baseViewsPath))->handle($action); return;
        case 'comments':    (new \Cms\Http\Admin\CommentsController($this->baseViewsPath))->handle($action); return;
        case 'themes':      (new \Cms\Http\Admin\ThemesController($this->baseViewsPath))->handle($action); return;
        case 'settings':    (new \Cms\Http\Admin\SettingsController($this->baseViewsPath))->handle($action); return;
        case 'migrations':  (new \Cms\Http\Admin\MigrationsController($this->baseViewsPath))->handle($action); return;
        case 'users': (new \Cms\Http\Admin\UsersController($this->baseViewsPath))->handle($action); return;
        case 'dashboard':
        default: $this->dashboardIndex(); return;
    }
}

    private function dashboardIndex(): void
    {
        $data = [
            'pageTitle'   => 'Dashboard',
            'nav'         => $this->adminNav('dashboard'),
            'currentUser' => (new \Cms\Auth\AuthService())->user(),
            'flash'       => $this->takeFlash(),
        ];
        $this->view->render('dashboard/index', $data);
    }

    private function adminNav(string $active): array
    {
        $items = [
            ['key'=>'dashboard','label'=>'Dashboard','href'=>'admin.php?r=dashboard'],
            ['key'=>'posts:post','label'=>'Příspěvky','href'=>'admin.php?r=posts&type=post'],
            ['key'=>'posts:page','label'=>'Stránky','href'=>'admin.php?r=posts&type=page'],
            ['key'=>'posts:product','label'=>'Produkty','href'=>'admin.php?r=posts&type=product'],
            ['key'=>'media','label'=>'Média','href'=>'admin.php?r=media'],
            ['key'=>'terms','label'=>'Termy','href'=>'admin.php?r=terms'],
            ['key'=>'comments','label'=>'Komentáře','href'=>'admin.php?r=comments'],
            ['key'=>'users','label'=>'Uživatelé','href'=>'admin.php?r=users'],
            ['key'=>'settings','label'=>'Nastavení','href'=>'admin.php?r=settings'],
        ];
        foreach ($items as &$it) { $it['active'] = ($it['key'] === $active); }
        return $items;
    }

    private function takeFlash(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $f = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);
        return is_array($f) ? $f : null;
    }
}
