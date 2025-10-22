<?php
declare(strict_types=1);

use Cms\Admin\Auth\AuthService;
use Cms\Admin\Http\AdminAjaxRouter;
use Cms\Admin\Http\AjaxResponse;

require_once __DIR__ . '/load.php';

cms_bootstrap_config_or_redirect();
cms_bootstrap_cron();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$auth = new AuthService();
if (!$auth->check()) {
    AjaxResponse::error('Neautorizovaný přístup.', 401)->send();
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method !== 'GET') {
    $sessionToken = isset($_SESSION['csrf_admin']) ? (string)$_SESSION['csrf_admin'] : '';
    $incomingToken = isset($_POST['csrf']) ? (string)$_POST['csrf'] : (string)($_SERVER['HTTP_X_CSRF'] ?? '');

    if ($sessionToken === '' || $incomingToken === '' || !hash_equals($sessionToken, $incomingToken)) {
        AjaxResponse::error('Neplatný CSRF token.', 419)->send();
    }
}

$actionParam = $_REQUEST['action'] ?? null;
$action = is_scalar($actionParam) ? (string)$actionParam : '';

$router = AdminAjaxRouter::instance();

$bootstrapFile = __DIR__ . '/admin/ajax.php';
if (is_file($bootstrapFile)) {
    require_once $bootstrapFile;
}

$response = $router->dispatch($action);
$response->send();
