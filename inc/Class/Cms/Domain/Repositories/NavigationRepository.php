<?php
declare(strict_types=1);

namespace Cms\Domain\Repositories;

use Core\Database\Init as DB;

final class NavigationRepository
{
    /**
     * Vrátí strom položek navigace pro danou lokaci.
     * @return array<int,array<string,mixed>>
     */
    public function treeByLocation(string $location): array
    {
        $pdo = DB::pdo();
        if (!$this->tableExists($pdo, 'navigation_menus') || !$this->tableExists($pdo, 'navigation_items')) {
            return [];
        }

        $menu = DB::query()->table('navigation_menus')
            ->select(['id'])
            ->where('location', '=', $location)
            ->orderBy('id', 'ASC')
            ->first();

        if (!$menu) {
            return [];
        }

        $items = DB::query()->table('navigation_items')
            ->select([
                'id',
                'parent_id',
                'title',
                'url',
                'target',
                'css_class',
            ])
            ->where('menu_id', '=', (int)$menu['id'])
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->get();

        if (!$items) {
            return [];
        }

        return $this->buildTree($items);
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array<string,mixed>>
     */
    private function buildTree(array $items): array
    {
        $children = [];
        foreach ($items as $item) {
            $parentId = isset($item['parent_id']) ? (int)$item['parent_id'] : 0;
            $children[$parentId][] = [
                'id'        => (int)$item['id'],
                'title'     => (string)$item['title'],
                'url'       => (string)$item['url'],
                'target'    => (string)($item['target'] ?? '_self'),
                'css_class' => (string)($item['css_class'] ?? ''),
            ];
        }

        return $this->attachChildren($children, 0);
    }

    /**
     * @param array<int,array<int,array<string,string|int>>> $map
     * @return array<int,array<string,mixed>>
     */
    private function attachChildren(array $map, int $parentId): array
    {
        $list = $map[$parentId] ?? [];
        foreach ($list as &$item) {
            $item['children'] = $this->attachChildren($map, (int)$item['id']);
        }
        unset($item);

        return $list;
    }

    private function tableExists(\PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    }
}
