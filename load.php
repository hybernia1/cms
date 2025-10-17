<?php
declare(strict_types=1);

/**
 * load.php (portable)
 * - Autoload tříd z /inc/Class
 */

// ---------------------------------------------------------
// Konstanty
// ---------------------------------------------------------
const BASE_DIR  = __DIR__;
const CLASS_DIR = __DIR__ . '/inc/Class';

//require_once BASE_DIR . '/inc/general_functions.php';

// ---------------------------------------------------------
// Autoload pro /inc/Class (PSR-4 + fallback s podtržítky)
// + automatické natažení helpers pro knihovny
// ---------------------------------------------------------
spl_autoload_register(
    static function (string $class): void {
        $class = ltrim($class, '\\');

        // Pomocná funkce: po načtení třídy zkusit přilinkovat helper soubor knihovny
        $loadHelpers = static function (string $loadedPath): void {
            static $loadedLibHelpers = []; // cache: 'libname' => true

            // Najdi kořenový adresář knihovny: první segment za CLASS_DIR
            // např. inc/Class/Medoo/Medoo.php -> 'Medoo'
            $rel = str_starts_with($loadedPath, CLASS_DIR . '/')
                ? substr($loadedPath, strlen(CLASS_DIR) + 1)
                : $loadedPath;

            $parts = explode('/', $rel);
            if (count($parts) < 2) return; // neočekávané (není ve tvaru Lib/Class.php)

            $lib = $parts[0];                        // název knihovny = první adresář
            $libKey = strtolower($lib);
            if (isset($loadedLibHelpers[$libKey])) return; // už načteno

            $dir = CLASS_DIR . '/' . $lib;

            // Kandidátní názvy helperů (pořadí důležité)
            $candidates = [
                $dir . '/' . $lib . '_helpers.php',          // Medoo_helpers.php
                $dir . '/helpers.php',                       // helpers.php
                $dir . '/' . strtolower($lib) . '_helpers.php', // medoo_helpers.php
                $dir . '/' . $lib . 'Helpers.php',           // MedooHelpers.php
            ];

            foreach ($candidates as $file) {
                if (is_file($file)) {
                    require_once $file;
                    $loadedLibHelpers[$libKey] = true;
                    break;
                }
            }
        };

        // 1) PSR-4: \Foo\Bar -> inc/Class/Foo/Bar.php
        $relativePath = str_replace('\\', '/', $class) . '.php';
        $path = CLASS_DIR . '/' . $relativePath;
        if (is_file($path)) {
            require_once $path;
            $loadHelpers($path);
            return;
        }

        // 2) Legacy fallback: Some_Legacy_Class -> inc/Class/Some/Legacy/Class.php
        if (str_contains($class, '_')) {
            $legacyPath = CLASS_DIR . '/' . str_replace('_', '/', $class) . '.php';
            if (is_file($legacyPath)) {
                require_once $legacyPath;
                $loadHelpers($legacyPath);
                return;
            }
        }
    },
    prepend: true
);

/**
 * Přesměruj na instalátor a ukonči skript.
 */
function cms_install_url(): string
{
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $scriptDir = str_replace('\\', '/', (string)dirname($scriptName));
    $scriptDir = trim($scriptDir, '/');
    if ($scriptDir === '.' || $scriptDir === '') {
        return '/install/';
    }

    return '/' . $scriptDir . '/install/';
}

function cms_redirect_to_install(): never
{
    header('Location: ' . cms_install_url());
    exit;
}

function cms_installation_error(string $message, ?Throwable $previous = null): never
{
    $isCli = (PHP_SAPI === 'cli');

    if ($isCli) {
        $output = "Installation error: {$message}";
        if ($previous) {
            $output .= "\n" . $previous->getMessage();
        }
        fwrite(STDERR, $output . "\n");
        exit(1);
    }

    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');

    $reason = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $target = htmlspecialchars(cms_install_url(), ENT_QUOTES, 'UTF-8');
    $details = '';

    if ($previous) {
        $details = htmlspecialchars($previous->getMessage(), ENT_QUOTES, 'UTF-8');
        $details = "<pre style=\"white-space:pre-wrap;\">{$details}</pre>";
    }

    echo <<<HTML
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title>Chyba konfigurace</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; background:#111; color:#eee; padding:3rem 1.5rem; }
    .card { max-width: 680px; margin: 0 auto; background: #1f1f1f; border-radius: .75rem; padding: 2rem; box-shadow: 0 1rem 2rem rgba(0,0,0,.35); }
    a { color: #61dafb; }
    pre { background: rgba(255,255,255,.05); padding:1rem; border-radius:.5rem; }
  </style>
</head>
<body>
  <div class="card">
    <h1>Chyba konfigurace</h1>
    <p>{$reason}</p>
    {$details}
    <p>
      Zkontroluj prosím <code>config.php</code> nebo znovu spusť instalátor.
      <br>
      <a href="{$target}">Přejít na instalátor</a>
    </p>
  </div>
</body>
</html>
HTML;

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

    if (!is_array($config) || !isset($config['db']) || !is_array($config['db'])) {
        cms_installation_error('Soubor config.php má neplatnou strukturu.');
    }

    try {
        \Core\Database\Init::boot($config);
    } catch (\Throwable $e) {
        cms_installation_error('Nepodařilo se navázat připojení k databázi. Zkontroluj prosím přihlašovací údaje.', $e);
    }

    return $config;
}

/**
 * Přesměruj na veřejnou login stránku. Pro AJAX požadavky vrať JSON odpověď.
 */
function cms_redirect_to_front_login(bool $success = false): never
{
    $target = 'login.php';
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if (!$isAjax) {
        $accept = isset($_SERVER['HTTP_ACCEPT']) ? (string)$_SERVER['HTTP_ACCEPT'] : '';
        $isAjax = str_contains($accept, 'application/json');
    }

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'  => $success,
            'redirect' => $target,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: ' . $target);
    exit;
}
