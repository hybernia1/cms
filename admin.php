<?php
declare(strict_types=1);

use Cms\Admin\Http\AdminController;
use Cms\Admin\Http\AdminAuthController;
use Cms\Admin\Auth\AuthService;
use Cms\Admin\Auth\Authorization;
use Cms\Admin\Domain\Services\QuickDraftService;
use Cms\Admin\Utils\AdminNavigation;
use Cms\Admin\View\ViewEngine;
use Core\Database\SchemaChecker;

require_once __DIR__ . '/load.php';

cms_bootstrap_config_or_redirect();

$sessionActive = session_status() === PHP_SESSION_ACTIVE;
if (!$sessionActive) {
    session_start();
}

$schemaChecker = new SchemaChecker();
$schemaChecker->preload();

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

    $baseViewPath = __DIR__ . '/admin/views';
    $view = new ViewEngine($baseViewPath);
    $paths = [$baseViewPath];
    $adminRoot = __DIR__ . '/admin';
    if (is_dir($adminRoot)) {
        $paths[] = $adminRoot;
    }
    $view->setBasePaths($paths);
    $view->render('errors/403', [
        'pageTitle'   => '403 – Přístup odepřen',
        'nav'         => AdminNavigation::build('dashboard'),
        'currentUser' => $user,
        'flash'       => $flash,
    ]);
    exit;
}

$quickDraftService = new QuickDraftService(auth: $auth);

$controller = new AdminController(
    baseViewsPath: __DIR__ . '/admin/views',
    quickDraftService: $quickDraftService,
    schemaChecker: $schemaChecker
);
$controller->handle($route, $action);
