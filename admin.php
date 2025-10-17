<?php
declare(strict_types=1);

use Cms\Http\AdminController;
use Cms\Http\AdminAuthController;
use Cms\Auth\AuthService;
use Cms\Auth\Authorization;
use Cms\Utils\AdminNavigation;
use Cms\View\ViewEngine;

require_once __DIR__ . '/load.php';

cms_bootstrap_config_or_redirect();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$route  = (string)($_GET['r'] ?? 'dashboard');
$action = (string)($_GET['a'] ?? 'index');

if ($route === 'auth') {
    (new AdminAuthController())->handle($action);
    exit;
}

$auth = new AuthService();
$user = $auth->user();
if (!$user) {
    (new AdminAuthController())->handle('login');
    exit;
}

// >>> ROLE GUARD: pouze admin <<<
if (!Authorization::isAdmin($user)) {
    http_response_code(403);

    $flash = null;
    if (isset($_SESSION['_flash']) && is_array($_SESSION['_flash'])) {
        $flash = $_SESSION['_flash'];
    }
    unset($_SESSION['_flash']);

    $view = new ViewEngine(__DIR__ . '/admin/views');
    $view->render('errors/403', [
        'pageTitle'   => '403 – Přístup odepřen',
        'nav'         => AdminNavigation::build('dashboard'),
        'currentUser' => $user,
        'flash'       => $flash,
    ]);
    exit;
}

$controller = new AdminController(baseViewsPath: __DIR__ . '/admin/views');
$controller->handle($route, $action);
