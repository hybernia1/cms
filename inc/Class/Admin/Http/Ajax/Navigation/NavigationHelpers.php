<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Navigation;

use Cms\Admin\Utils\Slugger;
use Core\Database\Init as DB;
use Core\Navigation\LinkResolver;

trait NavigationHelpers
{
    private ?LinkResolver $linkResolverInstance = null;

    protected function tablesReady(): bool
    {
        return $this->tableExists('navigation_menus') && $this->tableExists('navigation_items');
    }

    private function tableExists(string $table): bool
    {
        $pdo = DB::pdo();
        $sql = 'SHOW TABLES LIKE ' . $pdo->quote($table);
        $stmt = $pdo->query($sql);

        return (bool) $stmt->fetchColumn();
    }

    protected function linkResolver(): LinkResolver
    {
        if ($this->linkResolverInstance === null) {
            $this->linkResolverInstance = new LinkResolver();
        }

        return $this->linkResolverInstance;
    }

    protected function sanitizeLocation(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            $trimmed = 'primary';
        }
        $lower = mb_strtolower($trimmed, 'UTF-8');
        if (strlen($lower) > 64) {
            $lower = substr($lower, 0, 64);
        }

        return $lower;
    }

    protected function humanizeLocation(string $location): string
    {
        $normalized = str_replace(['-', '_'], ' ', $location);
        $normalized = preg_replace('~\s+~u', ' ', $normalized ?? '') ?? '';
        $normalized = trim($normalized);
        if ($normalized === '') {
            return 'Menu';
        }

        return ucwords(mb_strtolower($normalized, 'UTF-8'));
    }

    protected function menuByLocation(string $location, ?int $excludeId = null): ?array
    {
        $query = DB::query()
            ->table('navigation_menus')
            ->select(['id', 'name', 'location'])
            ->where('location', '=', $location);

        if ($excludeId !== null && $excludeId > 0) {
            $query->where('id', '!=', $excludeId);
        }

        $row = $query->first();

        return $row ?: null;
    }

    protected function findMenu(int $menuId): ?array
    {
        if ($menuId <= 0 || !$this->tablesReady()) {
            return null;
        }

        $row = DB::query()
            ->table('navigation_menus')
            ->select(['id', 'name', 'slug', 'location', 'description'])
            ->where('id', '=', $menuId)
            ->first();

        return $row ?: null;
    }

    protected function sanitizeSlug(string $value): string
    {
        $slug = Slugger::make($value);
        if (strlen($slug) > 64) {
            $slug = substr($slug, 0, 64);
        }

        return $slug !== '' ? $slug : 'menu';
    }

    protected function ensureUniqueSlug(string $slug, ?int $excludeId = null): string
    {
        $base = $slug;
        $i = 2;
        while ($this->slugExists($slug, $excludeId)) {
            $suffix = '-' . $i;
            $slug = substr($base, 0, 64 - strlen($suffix)) . $suffix;
            $i++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $query = DB::query()
            ->table('navigation_menus')
            ->select(['id'])
            ->where('slug', '=', $slug);

        if ($excludeId !== null && $excludeId > 0) {
            $query->where('id', '!=', $excludeId);
        }

        return (bool) $query->first();
    }

    protected function sanitizeTarget(string $target): string
    {
        $allowed = ['_self', '_blank'];

        return in_array($target, $allowed, true) ? $target : '_self';
    }

    protected function linkTypeLabels(): array
    {
        return [
            'custom' => 'Vlastní URL',
            'page' => 'Stránka',
            'post' => 'Příspěvek',
            'category' => 'Kategorie',
            'route' => 'Systémová stránka',
        ];
    }

    protected function linkStatusMessages(): array
    {
        return [
            'custom-empty' => 'URL není vyplněné.',
            'invalid-reference' => 'Vybraný obsah již neexistuje.',
            'missing' => 'Vybraný obsah nebyl nalezen.',
            'unpublished' => 'Obsah není publikován.',
            'unknown-route' => 'Neznámý typ systémového odkazu.',
            'error' => 'Nepodařilo se ověřit odkaz.',
        ];
    }

    protected function sanitizeLinkType(string $type): string
    {
        $allowed = array_keys($this->linkTypeLabels());

        return in_array($type, $allowed, true) ? $type : 'custom';
    }

    protected function prepareLinkData(string $type, string $reference, string $url): array
    {
        $normalizedType = $this->sanitizeLinkType($type);
        $normalizedReference = $normalizedType === 'custom' ? '' : trim($reference);
        $candidate = [
            'link_type' => $normalizedType,
            'link_reference' => $normalizedReference,
            'url' => $url,
        ];

        $resolved = $this->linkResolver()->resolve($candidate);
        $finalType = $resolved['type'];
        $finalReference = $finalType === 'custom' ? '' : $resolved['reference'];
        $finalUrl = $finalType === 'custom' ? trim($resolved['url']) : $resolved['url'];

        return [
            'type' => $finalType,
            'reference' => $finalReference,
            'url' => $finalUrl,
            'valid' => $resolved['valid'],
            'reason' => $resolved['reason'],
        ];
    }

    protected function allItemsForMenu(int $menuId): array
    {
        if ($menuId <= 0 || !$this->tablesReady()) {
            return [];
        }

        return DB::query()
            ->table('navigation_items')
            ->select(['id', 'menu_id', 'parent_id', 'title', 'link_type', 'link_reference', 'url', 'target', 'css_class', 'sort_order'])
            ->where('menu_id', '=', $menuId)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->get() ?? [];
    }

    protected function descendantIdsFromList(array $items, int $itemId): array
    {
        $map = [];
        foreach ($items as $item) {
            $parent = isset($item['parent_id']) ? (int)$item['parent_id'] : 0;
            $map[$parent][] = (int)$item['id'];
        }

        return $this->collectDescendants($map, $itemId);
    }

    private function collectDescendants(array $map, int $parentId): array
    {
        $children = $map[$parentId] ?? [];
        $all = [];
        foreach ($children as $child) {
            $all[] = $child;
            $all = array_merge($all, $this->collectDescendants($map, $child));
        }

        return $all;
    }
}
