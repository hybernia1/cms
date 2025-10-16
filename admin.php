<?php
declare(strict_types=1);

use Core\Database\Init as DB;
use Cms\Http\AdminController;
use Cms\Http\AdminAuthController;
use Cms\Auth\AuthService;
use Cms\Auth\Authorization;
use Cms\Utils\AdminNavigation;

require_once __DIR__ . '/load.php';

$config = require __DIR__ . '/config.php';
DB::boot($config);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$route  = (string)($_GET['r'] ?? 'dashboard');
$action = (string)($_GET['a'] ?? 'index');

if ($route === 'auth') {
    (new AdminAuthController(baseViewsPath: __DIR__ . '/admin/views'))->handle($action);
    exit;
}

$auth = new AuthService();
$user = $auth->user();
if (!$user) {
    header('Location: admin.php?r=auth&a=login');
    exit;
}

// >>> ROLE GUARD: pouze admin <<<
if (!Authorization::isAdmin($user)) {
    http_response_code(403);
    // jednoduché zobrazení 403 v admin layoutu:
    $view = new \Cms\View\ViewEngine(__DIR__ . '/admin/views');
    $view->render('errors/403', [
        'pageTitle'   => 'Přístup odepřen',
        'nav'         => AdminNavigation::build('dashboard'),
        'currentUser' => $user,
    ]);
    exit;
}

$controller = new AdminController(baseViewsPath: __DIR__ . '/admin/views');
$controller->handle($route, $action);
