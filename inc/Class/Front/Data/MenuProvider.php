<?php
declare(strict_types=1);

namespace Cms\Front\Data;

use Core\Database\Init as DB;
use Core\Navigation\LinkResolver;
use Throwable;

final class MenuProvider
{
    /** @var array<string,mixed> */
    private array $cache = [];
    private LinkResolver $linkResolver;

    public function __construct(?LinkResolver $resolver = null)
    {
        $this->linkResolver = $resolver ?? new LinkResolver();
    }

    /**
     * @return array<string,array<int,array<string,mixed>>>
     */
    public function menusByLocation(): array
    {
        if (isset($this->cache['locations'])) {
            /** @var array<string,array<int,array<string,mixed>>> $cached */
            $cached = $this->cache['locations'];
            return $cached;
        }

        try {
            $menus = DB::query()
                ->table('navigation_menus')
                ->select(['id','name','slug','location','description'])
                ->orderBy('name', 'ASC')
                ->get() ?? [];
        } catch (Throwable $e) {
            error_log('Failed to load navigation menus: ' . $e->getMessage());
            $this->cache['locations'] = [];
            return [];
        }

        if (!$menus) {
            $this->cache['locations'] = [];
            return [];
        }

        $menuMap = [];
        foreach ($menus as $menu) {
            $id = (int)($menu['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $menuMap[$id] = [
                'id' => $id,
                'name' => (string)($menu['name'] ?? ''),
                'slug' => (string)($menu['slug'] ?? ''),
                'location' => (string)($menu['location'] ?? 'primary'),
                'description' => (string)($menu['description'] ?? ''),
                'items' => [],
            ];
        }

        if ($menuMap === []) {
            $this->cache['locations'] = [];
            return [];
        }

        $menuIds = array_keys($menuMap);
        try {
            $items = DB::query()
                ->table('navigation_items')
                ->select(['id','menu_id','parent_id','title','link_type','link_reference','url','target','css_class','sort_order'])
                ->whereIn('menu_id', $menuIds)
                ->orderBy('sort_order', 'ASC')
                ->get() ?? [];
        } catch (Throwable $e) {
            error_log('Failed to load navigation items: ' . $e->getMessage());
            $this->cache['locations'] = [];
            return [];
        }

        $grouped = [];
        foreach ($items as $item) {
            $menuId = (int)($item['menu_id'] ?? 0);
            if (!isset($menuMap[$menuId])) {
                continue;
            }

            $link = $this->linkResolver->resolve($item);
            if (!$link['valid'] || $link['url'] === '') {
                continue;
            }

            $parent = isset($item['parent_id']) ? (int)$item['parent_id'] : 0;
            $grouped[$menuId][$parent][] = [
                'id' => (int)($item['id'] ?? 0),
                'menu_id' => $menuId,
                'parent_id' => $parent > 0 ? $parent : null,
                'title' => (string)($item['title'] ?? ''),
                'url' => $link['url'],
                'target' => (string)($item['target'] ?? '_self'),
                'css_class' => (string)($item['css_class'] ?? ''),
                'sort_order' => (int)($item['sort_order'] ?? 0),
                'link_type' => $link['type'],
                'link_reference' => $link['reference'],
                'link_meta' => $link['meta'],
                'link_valid' => $link['valid'],
                'link_reason' => $link['reason'],
            ];
        }

        $locations = [];
        foreach ($menuMap as $id => $menu) {
            $tree = $this->buildTree($grouped[$id] ?? []);
            $menu['items'] = $tree;
            $locations[$menu['location']] = $menu;
        }

        $this->cache['locations'] = $locations;

        return $locations;
    }

    /**
     * @param array<int,array<int,array<string,mixed>>> $map
     * @return array<int,array<string,mixed>>
     */
    private function buildTree(array $map, int $parentId = 0): array
    {
        $list = $map[$parentId] ?? [];
        foreach ($list as &$item) {
            $item['children'] = $this->buildTree($map, (int)$item['id']);
        }
        unset($item);
        return $list;
    }
}
