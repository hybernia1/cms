<?php
declare(strict_types=1);

use Cms\Http\AdminController;
use Cms\Http\AdminAuthController;
use Cms\Auth\AuthService;
use Cms\Auth\Authorization;

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
    cms_redirect_to_front_login();
}

// >>> ROLE GUARD: pouze admin <<<
if (!Authorization::isAdmin($user)) {
    cms_redirect_to_front_login();
}

$controller = new AdminController(baseViewsPath: __DIR__ . '/admin/views');
$controller->handle($route, $action);
