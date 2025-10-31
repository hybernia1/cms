<?php
declare(strict_types=1);

namespace Cms\Admin\Utils;

use Core\Plugins\PluginRegistry;
use Core\Widgets\WidgetRegistry;

/**
 * Central builder for admin navigation.
 * Provides consistent structure for the sidebar including hierarchy data
 * and active state flags for both top-level and nested links.
 */
final class AdminNavigation
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function build(string $activeKey): array
    {
        $pluginChildren = [];
        foreach (PluginRegistry::all() as $plugin) {
            $pluginChildren[] = [
                'key'   => 'plugins:' . $plugin['slug'],
                'label' => $plugin['name'],
                'href'  => 'admin.php?r=plugins&plugin=' . urlencode($plugin['slug']),
                'icon'  => 'bi-plug',
            ];
        }

        $widgetChildren = [];
        foreach (WidgetRegistry::all() as $widget) {
            $widgetChildren[] = [
                'key'   => 'widgets:' . $widget['id'],
                'label' => $widget['name'],
                'href'  => 'admin.php?r=widgets&widget=' . urlencode($widget['id']),
                'icon'  => 'bi-grid',
            ];
        }

        $items = [
            [
                'key'       => 'dashboard',
                'label'     => 'Nástěnka',
                'href'      => 'admin.php?r=dashboard',
                'icon'      => 'bi-speedometer2',
                'children'  => [],
            ],
            [
                'key'       => 'commerce',
                'label'     => 'Obchod',
                'href'      => null,
                'icon'      => 'bi-bag',
                'section'   => true,
                'children'  => [
                    ['key' => 'products',   'label' => 'Produkty',      'href' => 'admin.php?r=products',   'icon' => 'bi-box'],
                    ['key' => 'categories', 'label' => 'Kategorie',     'href' => 'admin.php?r=categories', 'icon' => 'bi-collection'],
                    ['key' => 'stock',      'label' => 'Sklad',         'href' => 'admin.php?r=stock',      'icon' => 'bi-archive'],
                    ['key' => 'orders',     'label' => 'Objednávky',    'href' => 'admin.php?r=orders',     'icon' => 'bi-receipt'],
                    ['key' => 'media',      'label' => 'Média',         'href' => 'admin.php?r=media',      'icon' => 'bi-images'],
                ],
            ],
            [
                'key'       => 'users',
                'label'     => 'Uživatelé',
                'href'      => 'admin.php?r=users',
                'icon'      => 'bi-people',
                'children'  => [],
            ],
            [
                'key'       => 'appearance',
                'label'     => 'Vzhled',
                'href'      => null,
                'icon'      => 'bi-palette',
                'section'   => true,
                'children'  => [
                    ['key' => 'themes',     'label' => 'Šablony',  'href' => 'admin.php?r=themes',     'icon' => 'bi-brush'],
                    ['key' => 'navigation', 'label' => 'Navigace', 'href' => 'admin.php?r=navigation', 'icon' => 'bi-list-ul'],
                ],
            ],
        ];

        $items[] = [
            'key'       => 'plugins',
            'label'     => 'Pluginy',
            'href'      => 'admin.php?r=plugins',
            'icon'      => 'bi-plug',
            'section'   => true,
            'children'  => $pluginChildren,
        ];

        $items[] = [
            'key'       => 'widgets',
            'label'     => 'Widgety',
            'href'      => 'admin.php?r=widgets',
            'icon'      => 'bi-grid',
            'section'   => true,
            'children'  => $widgetChildren,
        ];

        $items[] = [
            'key'       => 'settings',
            'label'     => 'Nastavení',
            'href'      => null,
            'icon'      => 'bi-gear',
            'section'   => true,
            'children'  => [
                ['key' => 'settings:general',    'label' => 'Obecné',   'href' => 'admin.php?r=settings',    'icon' => 'bi-sliders'],
                ['key' => 'settings:graphics',   'label' => 'Grafika webu', 'href' => 'admin.php?r=settings&a=graphics', 'icon' => 'bi-image'],
                ['key' => 'settings:permalinks','label' => 'Trvalé odkazy', 'href' => 'admin.php?r=settings&a=permalinks', 'icon' => 'bi-link-45deg'],
                ['key' => 'settings:mail',       'label' => 'E-mail',   'href' => 'admin.php?r=settings&a=mail', 'icon' => 'bi-envelope'],
                ['key' => 'settings:migrations', 'label' => 'Migrace',  'href' => 'admin.php?r=migrations', 'icon' => 'bi-arrow-repeat'],
            ],
        ];

        foreach ($items as &$item) {
            $children = $item['children'] ?? [];
            $itemIsActive = $item['key'] === $activeKey;

            foreach ($children as &$child) {
                $child['active'] = ($child['key'] === $activeKey);
                if (!isset($child['icon'])) {
                    $child['icon'] = null;
                }
                if ($child['active']) {
                    $itemIsActive = true;
                }
            }
            unset($child);

            $item['children'] = $children;
            $item['active'] = $itemIsActive;
            $item['expanded'] = $itemIsActive;
            if (!isset($item['icon'])) {
                $item['icon'] = null;
            }
        }
        unset($item);

        return $items;
    }
}

