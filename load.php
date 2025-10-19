<?php
declare(strict_types=1);

/**
 * load.php (portable, čistý)
 * - Autoload tříd z /inc/Class (PSR-4 + legacy underscore fallback)
 * - Volitelné globální helpery z /helpers (každý *.php v adresáři)
 */

// ---------------------------------------------------------
// Konstanty
// ---------------------------------------------------------
const BASE_DIR    = __DIR__;
const CLASS_DIR   = __DIR__ . '/inc/Class';
const HELPERS_DIR = __DIR__ . '/helpers';

//require_once BASE_DIR . '/inc/general_functions.php';

// ---------------------------------------------------------
// Autoload pro /inc/Class (PSR-4 + fallback s podtržítky)
// ---------------------------------------------------------
spl_autoload_register(
    static function (string $class): void {
        $class = ltrim($class, '\\');

        // 1) PSR-4: \Foo\Bar -> inc/Class/Foo/Bar.php
        $relativePath = str_replace('\\', '/', $class) . '.php';
        $path = CLASS_DIR . '/' . $relativePath;
        if (is_file($path)) {
            require_once $path;
            return;
        }

        // 2) Legacy fallback: Some_Legacy_Class -> inc/Class/Some/Legacy/Class.php
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
// Globální helpery (volitelné): načti všechny *.php z /helpers
// ---------------------------------------------------------
if (is_dir(HELPERS_DIR)) {
    $entries = scandir(HELPERS_DIR);
    if ($entries !== false) {
        sort($entries, SORT_STRING);
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (!str_ends_with($entry, '.php')) {
                continue;
            }
            $path = HELPERS_DIR . '/' . $entry;
            if (is_file($path)) {
                require_once $path;
            }
        }
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

    return $config;
}

