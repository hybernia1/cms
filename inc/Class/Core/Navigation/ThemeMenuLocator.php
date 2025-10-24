<?php
declare(strict_types=1);

namespace Core\Navigation;

use Cms\Admin\Settings\CmsSettings;

final class ThemeMenuLocator
{
    private string $themesDir;
    private CmsSettings $settings;

    /**
     * @var array<string,array<string,array{label:string,description:?string}>>
     */
    private static array $cache = [];

    public function __construct(?CmsSettings $settings = null, ?string $baseDir = null)
    {
        $this->settings = $settings ?? new CmsSettings();
        $root = $baseDir ?? (defined('BASE_DIR') ? BASE_DIR : dirname(__DIR__, 4));
        $this->themesDir = rtrim($root, DIRECTORY_SEPARATOR) . '/themes';
    }

    /**
     * @return array<string,array{label:string,description:?string}>
     */
    public function activeLocations(): array
    {
        return $this->locationsForTheme($this->settings->themeSlug());
    }

    /**
     * @return array<string,array{label:string,description:?string}>
     */
    public function locationsForTheme(string $slug): array
    {
        $trimmedSlug = trim($slug);
        if ($trimmedSlug === '') {
            return $this->fallbackLocations();
        }

        if (isset(self::$cache[$trimmedSlug])) {
            return self::$cache[$trimmedSlug];
        }

        MenuLocations::reset();

        foreach ($this->menusFromManifest($trimmedSlug) as $location => $info) {
            MenuLocations::register($location, [
                'label' => $info['label'],
                'description' => $info['description'],
            ]);
        }

        $functions = $this->themesDir . '/' . $trimmedSlug . '/functions.php';
        if (is_file($functions)) {
            try {
                require_once $functions;
            } catch (\Throwable $e) {
                error_log('Failed to bootstrap theme functions: ' . $e->getMessage());
            }
        }

        $registered = MenuLocations::all();
        if ($registered === []) {
            $registered = $this->fallbackLocations();
        }

        self::$cache[$trimmedSlug] = $registered;
        return $registered;
    }

    /**
     * @return array<string,array{label:string,description:?string}>
     */
    private function fallbackLocations(): array
    {
        $cacheKey = '__default__';
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        MenuLocations::reset();
        MenuLocations::register('primary', 'Primary menu', 'Výchozí umístění navigace');

        $registered = MenuLocations::all();
        self::$cache[$cacheKey] = $registered;

        return $registered;
    }

    /**
     * @return array<string,array{label:string,description:?string}>
     */
    private function menusFromManifest(string $slug): array
    {
        $file = $this->themesDir . '/' . $slug . '/theme.json';
        if (!is_file($file)) {
            return [];
        }
        $raw = file_get_contents($file);
        if ($raw === false) {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        $menusRaw = $decoded['menus'] ?? null;
        if (!is_array($menusRaw)) {
            $supports = $decoded['supports'] ?? null;
            if (is_array($supports) && is_array($supports['menus'] ?? null)) {
                $menusRaw = $supports['menus'];
            }
        }
        if (!is_array($menusRaw)) {
            return [];
        }

        $menus = [];
        foreach ($menusRaw as $key => $value) {
            if (is_int($key) && is_string($value) && $value !== '') {
                $location = $value;
                $menus[$location] = [
                    'label' => $this->humanize($location),
                    'description' => null,
                ];
                continue;
            }

            if (is_string($key) && is_string($value)) {
                $menus[$key] = [
                    'label' => trim($value) !== '' ? trim($value) : $this->humanize($key),
                    'description' => null,
                ];
                continue;
            }

            if (is_string($key) && is_array($value)) {
                $label = is_string($value['label'] ?? null) ? trim((string)$value['label']) : '';
                $description = isset($value['description']) && is_string($value['description'])
                    ? trim((string)$value['description'])
                    : null;
                if ($label === '') {
                    $label = $this->humanize($key);
                }
                $menus[$key] = [
                    'label' => $label,
                    'description' => $description !== '' ? $description : null,
                ];
                continue;
            }

            if (is_string($value ?? null) && $value !== '') {
                $location = (string)$value;
                $menus[$location] = [
                    'label' => $this->humanize($location),
                    'description' => null,
                ];
            }
        }

        return $menus;
    }

    private function humanize(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'Menu';
        }
        $normalized = str_replace(['-', '_'], ' ', $value);
        $normalized = preg_replace('~\s+~u', ' ', $normalized ?? '') ?? '';
        $normalized = trim($normalized);
        if ($normalized === '') {
            return 'Menu';
        }
        return ucwords(mb_strtolower($normalized, 'UTF-8'));
    }
}
