<?php
declare(strict_types=1);

/**
 * load.php (portable, čistý)
 * - Autoload tříd z /inc/Class (PSR-4 + legacy underscore fallback)
 * - Speciální mapování namespace pro inc/Class/Admin a inc/Class/Core
 */

// ---------------------------------------------------------
// Konstanty
// ---------------------------------------------------------
const BASE_DIR      = __DIR__;
const CLASS_DIR     = __DIR__ . '/inc/Class';
const FUNCTIONS_DIR = __DIR__ . '/inc/functions';
const MODELS_DIR    = __DIR__ . '/inc/models';
const PLUGINS_DIR   = __DIR__ . '/plugins';
const WIDGETS_DIR   = __DIR__ . '/widgets';

/**
 * @var array<string,string>
 */
const CLASS_NAMESPACE_MAP = [
    'Cms\\Admin\\' => __DIR__ . '/inc/Class/Admin',
    'Cms\\Front\\' => __DIR__ . '/inc/Class/Front',
    'Cms\\Models\\' => __DIR__ . '/inc/models',
    'Cms\\Services\\' => __DIR__ . '/inc/services',
    'Core\\'        => __DIR__ . '/inc/Class/Core',
];

// ---------------------------------------------------------
// Autoload pro /inc/Class (PSR-4 + fallback s podtržítky)
// ---------------------------------------------------------
spl_autoload_register(
    static function (string $class): void {
        $class = ltrim($class, '\\');

        // 1) Explicit namespace map for reorganized directories
        foreach (CLASS_NAMESPACE_MAP as $prefix => $directory) {
            if (str_starts_with($class, $prefix)) {
                $relative = substr($class, strlen($prefix));
                $path = rtrim($directory, '/\\') . '/' . str_replace('\\', '/', $relative) . '.php';
                if (is_file($path)) {
                    require_once $path;
                    return;
                }
            }
        }

        // 2) PSR-4 fallback: \Foo\Bar -> inc/Class/Foo/Bar.php
        $relativePath = str_replace('\\', '/', $class) . '.php';
        $path = CLASS_DIR . '/' . $relativePath;
        if (is_file($path)) {
            require_once $path;
            return;
        }

        // 3) Legacy fallback: Some_Legacy_Class -> inc/Class/Some/Legacy/Class.php
        if (str_contains($class, '_')) {
            $legacyPath = CLASS_DIR . '/' . str_replace('_', '/', $class) . '.php';
            if (is_file($legacyPath)) {
                require_once $legacyPath;
                return;
            }
        }
    },
    prepend: true
);

// ---------------------------------------------------------
// Database helpers (global procedural utilities)
// ---------------------------------------------------------
if (is_file(__DIR__ . '/inc/db.php')) {
    require_once __DIR__ . '/inc/db.php';
}

if (is_dir(FUNCTIONS_DIR)) {
    /** @var list<string> $functionFiles */
    $functionFiles = glob(FUNCTIONS_DIR . '/*.php') ?: [];
    sort($functionFiles);

    foreach ($functionFiles as $file) {
        require_once $file;
    }
}

// ---------------------------------------------------------
// Služby (košík, objednávky)
// ---------------------------------------------------------

if (is_file(__DIR__ . '/inc/services/cart.php')) {
    require_once __DIR__ . '/inc/services/cart.php';
    if (function_exists('cms_cart_boot')) {
        cms_cart_boot();
    }
}

if (is_file(__DIR__ . '/inc/services/order.php')) {
    require_once __DIR__ . '/inc/services/order.php';
}

// ---------------------------------------------------------
// Util: redirecty a bootstrap
// ---------------------------------------------------------

/**
 * Přesměruj na instalátor a ukonči skript.
 */
function cms_redirect_to_install(): never
{
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $scriptDir = str_replace('\\', '/', (string)dirname($scriptName));
    $scriptDir = trim($scriptDir, '/');
    if ($scriptDir === '.') {
        $scriptDir = '';
    }

    $target = $scriptDir === '' ? '/install/' : '/' . $scriptDir . '/install/';

    header('Location: ' . $target);
    exit;
}

/**
 * Načti konfiguraci a ověř dostupnost databáze. Pokud chybí, přesměruj na instalátor.
 *
 * @return array<string,mixed>
 */
function cms_bootstrap_config_or_redirect(): array
{
    $configFile = BASE_DIR . '/config.php';
    if (!is_file($configFile)) {
        cms_redirect_to_install();
    }

    /** @var array<string,mixed> $config */
    $config = require $configFile;

    \Core\Database\Init::boot($config);

    static $extrasBootstrapped = false;
    if (!$extrasBootstrapped) {
        if (function_exists('cms_bootstrap_plugins')) {
            cms_bootstrap_plugins();
        }

        if (function_exists('cms_bootstrap_widgets')) {
            cms_bootstrap_widgets();
        }

        $extrasBootstrapped = true;
    }

    return $config;
}

// ---------------------------------------------------------
// HTTP obsluha košíku
// ---------------------------------------------------------

/**
 * @return never
 */
function cms_handle_cart_action(string $action): never
{
    cms_bootstrap_config_or_redirect();

    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method !== 'POST') {
        cms_cart_respond(cms_cart_error('Neplatná metoda požadavku.', 405));
    }

    $token = isset($_REQUEST['csrf']) ? (string)$_REQUEST['csrf'] : (isset($_POST['csrf']) ? (string)$_POST['csrf'] : '');
    if (!cms_cart_verify_csrf($token)) {
        cms_cart_respond(cms_cart_error('Formulář vypršel. Načtěte stránku a zkuste to prosím znovu.', 419));
    }

    $result = cms_cart_error('Akce nebyla nalezena.', 404);

    switch ($action) {
        case 'add':
            $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
            $variantRaw = $_POST['variant_id'] ?? null;
            $variantId = is_numeric($variantRaw) ? (int)$variantRaw : null;
            if ($variantId !== null && $variantId <= 0) {
                $variantId = null;
            }
            $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
            if ($productId <= 0) {
                $result = cms_cart_error('Vyberte prosím produkt.', 422);
                break;
            }
            $result = cms_cart_add_item($productId, $variantId, $quantity);
            break;
        case 'update':
            $itemId = isset($_POST['item_id']) ? (string)$_POST['item_id'] : '';
            $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
            if ($itemId === '') {
                $result = cms_cart_error('Chybí identifikátor položky.', 422);
                break;
            }
            $result = cms_cart_update_item($itemId, $quantity);
            break;
        case 'remove':
            $itemId = isset($_POST['item_id']) ? (string)$_POST['item_id'] : '';
            if ($itemId === '') {
                $result = cms_cart_error('Chybí identifikátor položky.', 422);
                break;
            }
            $result = cms_cart_remove_item($itemId);
            break;
        default:
            $result = cms_cart_error('Neznámá akce košíku.', 404);
    }

    cms_cart_respond($result);
}

/**
 * @param array{success:bool,message:string,status:int,cart:array{items:list<array<string,mixed>>,subtotal:float,total:float,currency:string,count:int,updated_at:?string}} $result
 */
function cms_cart_respond(array $result): never
{
    $status = $result['status'] ?? 200;
    http_response_code($status);

    if (cms_cart_wants_json()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $result['success'],
            'message' => $result['message'],
            'cart' => $result['cart'],
        ], JSON_THROW_ON_ERROR);
        exit;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['_front_notification'] ??= [];
        $_SESSION['_front_notification'][] = [
            'type' => $result['success'] ? 'success' : 'danger',
            'message' => $result['message'],
        ];
    }

    $redirect = isset($_POST['redirect']) ? (string)$_POST['redirect'] : '';
    if ($redirect === '') {
        $redirect = isset($_SERVER['HTTP_REFERER']) ? (string)$_SERVER['HTTP_REFERER'] : '';
    }
    if ($redirect === '') {
        $redirect = './';
    }

    header('Location: ' . $redirect, true, $status >= 400 ? 303 : 303);
    exit;
}

if (isset($_REQUEST['cart_action']) && function_exists('cms_cart_add_item')) {
    cms_handle_cart_action((string)$_REQUEST['cart_action']);
}

