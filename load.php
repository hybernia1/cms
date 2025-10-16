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