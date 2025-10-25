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
const PLUGINS_DIR   = __DIR__ . '/plugins';
const WIDGETS_DIR   = __DIR__ . '/widgets';

/**
 * @var array<string,string>
 */
const CLASS_NAMESPACE_MAP = [
    'Cms\\Admin\\' => __DIR__ . '/inc/Class/Admin',
    'Cms\\Front\\' => __DIR__ . '/inc/Class/Front',
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

if (is_dir(FUNCTIONS_DIR)) {
    /** @var list<string> $functionFiles */
    $functionFiles = glob(FUNCTIONS_DIR . '/*.php') ?: [];
    sort($functionFiles);

    foreach ($functionFiles as $file) {
        require_once $file;
    }
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

