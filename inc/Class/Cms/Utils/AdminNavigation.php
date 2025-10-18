<?php
declare(strict_types=1);

namespace Cms\Utils;

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
        $items = [
            [
                'key'       => 'dashboard',
                'label'     => 'Nástěnka',
                'href'      => 'admin.php?r=dashboard',
                'icon'      => 'bi-speedometer2',
                'children'  => [],
            ],
            [
                'key'       => 'content',
                'label'     => 'Obsah',
                'href'      => null,
                'icon'      => 'bi-folder2',
                'section'   => true,
                'children'  => [
                    ['key' => 'posts:post', 'label' => 'Příspěvky', 'href' => 'admin.php?r=posts&type=post', 'icon' => 'bi-file-earmark-text'],
                    ['key' => 'posts:page', 'label' => 'Stránky',   'href' => 'admin.php?r=posts&type=page', 'icon' => 'bi-file-earmark-richtext'],
                    ['key' => 'media',         'label' => 'Média',     'href' => 'admin.php?r=media',             'icon' => 'bi-images'],
                    ['key' => 'terms:category','label' => 'Kategorie', 'href' => 'admin.php?r=terms&type=category','icon' => 'bi-collection'],
                    ['key' => 'terms:tag',     'label' => 'Štítky',    'href' => 'admin.php?r=terms&type=tag',     'icon' => 'bi-hash'],
                    ['key' => 'comments',      'label' => 'Komentáře', 'href' => 'admin.php?r=comments',          'icon' => 'bi-chat-dots'],
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
            [
                'key'       => 'settings',
                'label'     => 'Nastavení',
                'href'      => null,
                'icon'      => 'bi-gear',
                'section'   => true,
                'children'  => [
                    ['key' => 'settings:general',    'label' => 'Obecné',   'href' => 'admin.php?r=settings',    'icon' => 'bi-sliders'],
                    ['key' => 'settings:permalinks','label' => 'Trvalé odkazy', 'href' => 'admin.php?r=settings&a=permalinks', 'icon' => 'bi-link-45deg'],
                    ['key' => 'settings:mail',       'label' => 'E-mail',   'href' => 'admin.php?r=settings&a=mail', 'icon' => 'bi-envelope'],
                    ['key' => 'settings:migrations', 'label' => 'Migrace',  'href' => 'admin.php?r=migrations', 'icon' => 'bi-arrow-repeat'],
                ],
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

