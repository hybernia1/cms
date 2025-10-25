<?php
declare(strict_types=1);

use Cms\Front\View\ThemeCommentsData;
use Cms\Front\View\ThemeContext;
use Cms\Front\View\ThemeMetaData;
use Cms\Front\View\ThemeNavigationData;
use Cms\Front\View\ThemeSiteData;
use Cms\Front\View\ThemeThemeData;

if (!function_exists('theme_context')) {
    function theme_context(): ThemeContext
    {
        return ThemeContext::instance();
    }
}

if (!function_exists('theme_site')) {
    /**
     * @return ThemeSiteData|string
     */
    function theme_site(?string $key = null, mixed $default = null)
    {
        $site = theme_context()->site();
        if ($key === null) {
            return $site;
        }

        return $site->value($key, $default);
    }
}

if (!function_exists('theme_meta')) {
    /**
     * @return ThemeMetaData|string|array|null
     */
    function theme_meta(?string $key = null, mixed $default = null)
    {
        $meta = theme_context()->meta();
        if ($key === null) {
            return $meta;
        }

        return $meta->value($key, $default);
    }
}

if (!function_exists('theme_theme')) {
    /**
     * @return ThemeThemeData|mixed
     */
    function theme_theme(?string $key = null, mixed $default = null)
    {
        $theme = theme_context()->theme();
        if ($key === null) {
            return $theme;
        }

        if ($key === 'palette') {
            return $theme->palette();
        }

        if ($key === 'asset') {
            return $theme->asset();
        }

        return $theme->value($key, $default);
    }
}

if (!function_exists('theme_palette')) {
    function theme_palette(?string $key = null, ?string $default = null): array|string|null
    {
        $theme = theme_context()->theme();
        if ($key === null) {
            return $theme->palette();
        }

        return $theme->paletteValue($key, $default);
    }
}

if (!function_exists('theme_navigation')) {
    function theme_navigation(?string $location = null): ThemeNavigationData|array
    {
        $navigation = theme_context()->navigation();
        if ($location === null) {
            return $navigation;
        }

        return $navigation->items($location);
    }
}

if (!function_exists('theme_menu')) {
    function theme_menu(string $location): ?array
    {
        $menu = theme_context()->navigation()->menu($location);
        return $menu?->toArray();
    }
}

if (!function_exists('theme_comments')) {
    /**
     * @return ThemeCommentsData|array|int|bool
     */
    function theme_comments(?string $key = null, mixed $default = null)
    {
        $comments = theme_context()->comments();
        if ($key === null) {
            return $comments;
        }

        return $comments->value($key, $default);
    }
}

if (!function_exists('theme_links')) {
    function theme_links(): \Cms\Admin\Utils\LinkGenerator
    {
        return theme_context()->links();
    }
}

if (!function_exists('theme_format')) {
    function theme_format(string $key): ?callable
    {
        $formatters = theme_context()->formatters();
        return $formatters[$key] ?? null;
    }
}
