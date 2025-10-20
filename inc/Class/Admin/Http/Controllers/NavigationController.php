<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Controllers;

use Cms\Admin\Utils\Slugger;
use Core\Database\Init as DB;
use Cms\Admin\Utils\AdminNavigation;
use Cms\Admin\Utils\DateTimeFactory;
use Cms\Admin\Utils\LinkGenerator;
use Core\Navigation\LinkResolver;
use Core\Navigation\ThemeMenuLocator;

final class NavigationController extends BaseAdminController
{
    private ?LinkResolver $linkResolver = null;

    public function handle(string $action): void
    {
        switch ($action) {
            case 'create-menu':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $this->createMenu();
                } else {
                    $this->index();
                }
                return;
            case 'update-menu':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $this->updateMenu();
                } else {
                    $this->index();
                }
                return;
            case 'delete-menu':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $this->deleteMenu();
                } else {
                    $this->index();
                }
                return;
            case 'create-item':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $this->createItem();
                } else {
                    $this->index();
                }
                return;
            case 'update-item':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $this->updateItem();
                } else {
                    $this->index();
                }
                return;
            case 'delete-item':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $this->deleteItem();
                } else {
                    $this->index();
                }
                return;
            case 'index':
            default:
                $this->index();
                return;
        }
    }


    private function redirectTo(?int $menuId = null, array $extra = []): never
    {
        $params = array_merge(['r' => 'navigation'], $extra);
        if ($menuId) {
            $params['menu_id'] = $menuId;
        }

        $this->redirect('admin.php?' . http_build_query($params));
    }

    private function tablesReady(): bool
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

    private function linkResolver(): LinkResolver
    {
        if ($this->linkResolver === null) {
            $this->linkResolver = new LinkResolver();
        }

        return $this->linkResolver;
    }

    private function loadMenus(): array
    {
        if (!$this->tablesReady()) {
            return [];
        }
        return DB::query()
            ->table('navigation_menus')
            ->select(['id', 'name', 'slug', 'location', 'description', 'created_at', 'updated_at'])
            ->orderBy('name', 'ASC')
            ->get() ?? [];
    }

    /**
     * @param array<int,array<string,mixed>> $menus
     * @return array<string,array{value:string,label:string,description:?string,assigned_menu_id:?int,assigned_menu_name:?string}>
     */
    private function menuLocationOptions(array $menus): array
    {
        $locator = new ThemeMenuLocator();
        $locations = $locator->activeLocations();

        $assigned = [];
        foreach ($menus as $menu) {
            $loc = isset($menu['location']) ? (string)$menu['location'] : '';
            if ($loc === '') {
                continue;
            }
            $normalized = $this->sanitizeLocation($loc);
            if ($normalized === '') {
                continue;
            }
            $assigned[$normalized] = [
                'id' => (int)($menu['id'] ?? 0),
                'name' => (string)($menu['name'] ?? ''),
            ];
        }

        $options = [];
        foreach ($locations as $key => $info) {
            $value = $this->sanitizeLocation((string)$key);
            if ($value === '') {
                continue;
            }
            $label = is_string($info['label'] ?? null) ? trim((string)$info['label']) : '';
            if ($label === '') {
                $label = $this->humanizeLocation($value);
            }
            $description = isset($info['description']) && is_string($info['description'])
                ? trim((string)$info['description'])
                : null;
            $assignedMenu = $assigned[$value] ?? null;
            $options[$value] = [
                'value' => $value,
                'label' => $label,
                'description' => $description !== '' ? $description : null,
                'assigned_menu_id' => $assignedMenu['id'] ?? null,
                'assigned_menu_name' => $assignedMenu['name'] ?? null,
            ];
        }

        foreach ($assigned as $value => $menuInfo) {
            if (!isset($options[$value])) {
                $options[$value] = [
                    'value' => $value,
                    'label' => $this->humanizeLocation($value),
                    'description' => null,
                    'assigned_menu_id' => $menuInfo['id'],
                    'assigned_menu_name' => $menuInfo['name'],
                ];
            }
        }

        ksort($options);

        return $options;
    }

    private function humanizeLocation(string $location): string
    {
        $normalized = str_replace(['-', '_'], ' ', $location);
        $normalized = preg_replace('~\s+~u', ' ', $normalized ?? '') ?? '';
        $normalized = trim($normalized);
        if ($normalized === '') {
            return 'Menu';
        }
        return ucwords(mb_strtolower($normalized, 'UTF-8'));
    }

    private function sanitizeLocation(string $value): string
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

    private function menuByLocation(string $location, ?int $excludeId = null): ?array
    {
        $query = DB::query()
            ->table('navigation_menus')
            ->select(['id', 'name', 'location'])
            ->where('location', '=', $location);

        if ($excludeId !== null && $excludeId > 0) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->first() ?: null;
    }

    private function findMenu(int $menuId): ?array
    {
        if ($menuId <= 0 || !$this->tablesReady()) {
            return null;
        }
        return DB::query()
            ->table('navigation_menus')
            ->select(['id', 'name', 'slug', 'location', 'description'])
            ->where('id', '=', $menuId)
            ->first() ?: null;
    }

    private function allItemsForMenu(int $menuId): array
    {
        if ($menuId <= 0 || !$this->tablesReady()) {
            return [];
        }
        return DB::query()
            ->table('navigation_items')
            ->select(['id', 'menu_id', 'parent_id', 'title', 'link_type', 'link_reference', 'url', 'target', 'css_class', 'sort_order', 'created_at'])
            ->where('menu_id', '=', $menuId)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->get() ?? [];
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array<string,mixed>>
     */
    private function resolveItems(array $items): array
    {
        $resolver = $this->linkResolver();
        $resolved = [];
        foreach ($items as $item) {
            $linkData = $resolver->resolve($item);
            $item['link_type'] = $linkData['type'];
            $item['link_reference'] = $linkData['reference'];
            $item['url'] = $linkData['url'];
            $item['link_valid'] = $linkData['valid'];
            $item['link_reason'] = $linkData['reason'];
            $item['link_meta'] = $linkData['meta'];
            $resolved[] = $item;
        }

        return $resolved;
    }

    private function buildTree(array $items): array
    {
        $children = [];
        foreach ($items as $item) {
            $parent = isset($item['parent_id']) ? (int)$item['parent_id'] : 0;
            $children[$parent][] = [
                'id' => (int)$item['id'],
                'menu_id' => (int)$item['menu_id'],
                'parent_id' => $parent > 0 ? $parent : null,
                'title' => (string)$item['title'],
                'link_type' => (string)($item['link_type'] ?? 'custom'),
                'link_reference' => (string)($item['link_reference'] ?? ''),
                'link_valid' => (bool)($item['link_valid'] ?? false) || (string)($item['link_type'] ?? 'custom') === 'custom',
                'link_reason' => $item['link_reason'] ?? null,
                'link_meta' => is_array($item['link_meta'] ?? null) ? $item['link_meta'] : [],
                'url' => (string)($item['url'] ?? ''),
                'target' => (string)($item['target'] ?? '_self'),
                'css_class' => (string)($item['css_class'] ?? ''),
                'sort_order' => (int)$item['sort_order'],
                'created_at' => (string)$item['created_at'],
            ];
        }
        return $this->attachChildren($children, 0);
    }

    private function attachChildren(array $map, int $parentId): array
    {
        $list = $map[$parentId] ?? [];
        foreach ($list as &$item) {
            $item['children'] = $this->attachChildren($map, (int)$item['id']);
        }
        unset($item);
        return $list;
    }

    private function flattenTree(array $nodes, int $depth = 0): array
    {
        $result = [];
        foreach ($nodes as $node) {
            $children = $node['children'] ?? [];
            unset($node['children']);
            $node['depth'] = $depth;
            $result[] = $node;
            if ($children) {
                $result = array_merge($result, $this->flattenTree($children, $depth + 1));
            }
        }
        return $result;
    }

    private function descendantIdsFromList(array $items, int $itemId): array
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

    private function parentOptions(array $flat, ?int $excludeId, array $invalidIds): array
    {
        $options = [
            ['value' => 0, 'label' => '— Bez rodiče —', 'disabled' => false],
        ];
        foreach ($flat as $item) {
            $id = (int)$item['id'];
            $disabled = false;
            if ($excludeId && $id === $excludeId) {
                $disabled = true;
            }
            if (in_array($id, $invalidIds, true)) {
                $disabled = true;
            }
            $options[] = [
                'value' => $id,
                'label' => str_repeat('— ', $item['depth']) . $item['title'],
                'disabled' => $disabled,
            ];
        }
        return $options;
    }

    private function sanitizeSlug(string $value): string
    {
        $slug = Slugger::make($value);
        if (strlen($slug) > 64) {
            $slug = substr($slug, 0, 64);
        }
        return $slug !== '' ? $slug : 'menu';
    }

    private function ensureUniqueSlug(string $slug, ?int $excludeId = null): string
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
        $q = DB::query()
            ->table('navigation_menus')
            ->select(['id'])
            ->where('slug', '=', $slug);
        if ($excludeId) {
            $q->where('id', '!=', $excludeId);
        }
        return (bool) $q->first();
    }

    private function sanitizeTarget(string $target): string
    {
        $allowed = ['_self', '_blank'];
        return in_array($target, $allowed, true) ? $target : '_self';
    }

    private function targetOptions(): array
    {
        return [
            ['value' => '_self', 'label' => 'Stejné okno'],
            ['value' => '_blank', 'label' => 'Nové okno'],
        ];
    }

    /**
     * @return array<string,string>
     */
    private function linkTypeLabels(): array
    {
        return [
            'custom' => 'Vlastní URL',
            'page' => 'Stránka',
            'post' => 'Příspěvek',
            'category' => 'Kategorie',
            'route' => 'Systémová stránka',
        ];
    }

    /**
     * @return array<string,string>
     */
    private function linkStatusMessages(): array
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

    private function sanitizeLinkType(string $type): string
    {
        $allowed = array_keys($this->linkTypeLabels());
        return in_array($type, $allowed, true) ? $type : 'custom';
    }

    /**
     * @return array{type:string,reference:string,url:string,valid:bool,reason:?string}
     */
    private function prepareLinkData(string $type, string $reference, string $url): array
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

    private function index(): void
    {
        $tablesReady = $this->tablesReady();
        $menus = $this->loadMenus();
        $menuId = (int)($_GET['menu_id'] ?? 0);
        if ($menuId <= 0 && $menus) {
            $menuId = (int)($menus[0]['id'] ?? 0);
        }
        $menu = $menuId > 0 ? $this->findMenu($menuId) : null;
        if (!$menu && $menuId > 0) {
            $this->flash('warning', 'Požadované menu nebylo nalezeno.');
            $this->redirectTo(null);
        }

        $itemsRaw = $menu ? $this->allItemsForMenu((int)$menu['id']) : [];
        $itemsResolved = $itemsRaw ? $this->resolveItems($itemsRaw) : [];
        $tree = $this->buildTree($itemsResolved);
        $flat = $this->flattenTree($tree);

        $editingItem = null;
        $itemId = (int)($_GET['item_id'] ?? 0);
        if ($itemId > 0 && $menu) {
            $editingItem = DB::query()
                ->table('navigation_items')
                ->select(['id', 'menu_id', 'parent_id', 'title', 'link_type', 'link_reference', 'url', 'target', 'css_class', 'sort_order'])
                ->where('id', '=', $itemId)
                ->first() ?: null;
            if (!$editingItem || (int)$editingItem['menu_id'] !== (int)$menu['id']) {
                $editingItem = null;
            } else {
                $resolved = $this->linkResolver()->resolve($editingItem);
                $editingItem['link_type'] = $resolved['type'];
                $editingItem['link_reference'] = $resolved['reference'];
                $editingItem['url'] = $resolved['url'];
                $editingItem['link_valid'] = $resolved['valid'];
                $editingItem['link_reason'] = $resolved['reason'];
                $editingItem['link_meta'] = $resolved['meta'];
            }
        }

        $invalidParents = [];
        if ($editingItem) {
            $invalidParents = $this->descendantIdsFromList($itemsResolved ?: $itemsRaw, (int)$editingItem['id']);
        }
        $parentOptions = $this->parentOptions($flat, $editingItem ? (int)$editingItem['id'] : null, $invalidParents);

        $menuLocations = $this->menuLocationOptions($menus);
        $menuLocationValue = $menu ? $this->sanitizeLocation((string)$menu['location']) : null;

        $this->renderAdmin('navigation/index', [
            'pageTitle' => 'Navigace',
            'nav' => AdminNavigation::build('navigation'),
            'tablesReady' => $tablesReady,
            'menus' => $menus,
            'menu' => $menu,
            'menuId' => $menuId,
            'menuLocations' => $menuLocations,
            'menuLocationValue' => $menuLocationValue,
            'items' => $flat,
            'editingItem' => $editingItem,
            'parentOptions' => $parentOptions,
            'targets' => $this->targetOptions(),
            'quickAddOptions' => $this->quickAddOptions(),
            'linkTypeLabels' => $this->linkTypeLabels(),
            'linkStatusMessages' => $this->linkStatusMessages(),
        ]);
    }

    private function quickAddOptions(): array
    {
        $options = [
            'pages' => [],
            'posts' => [],
            'categories' => [],
            'system' => [],
        ];

        $links = new LinkGenerator();

        if ($this->tableExists('posts')) {
            $options['pages'] = $this->loadQuickPosts('page', $links);
            $options['posts'] = $this->loadQuickPosts('post', $links);
        }

        if ($this->tableExists('terms')) {
            $options['categories'] = $this->loadQuickCategories($links);
        }

        $options['system'] = $this->loadQuickSystem($links);

        return $options;
    }

    private function loadQuickPosts(string $type, LinkGenerator $links): array
    {
        $rows = DB::query()
            ->table('posts')
            ->select(['id', 'title', 'slug', 'status'])
            ->where('type', '=', $type)
            ->where('status', '=', 'publish')
            ->orderBy('title', 'ASC')
            ->limit(50)
            ->get() ?? [];

        $items = [];
        foreach ($rows as $row) {
            $title = trim((string)($row['title'] ?? ''));
            $slug = trim((string)($row['slug'] ?? ''));
            $id = (int)($row['id'] ?? 0);
            if ($title === '' || $slug === '') {
                continue;
            }
            if ($id <= 0) {
                continue;
            }

            $url = $type === 'page'
                ? $links->page($slug)
                : $links->post($slug);

            $items[] = [
                'id' => $id,
                'title' => $title,
                'slug' => $slug,
                'url' => $url,
                'type' => $type,
                'link_type' => $type,
                'link_reference' => (string)$id,
                'status' => (string)($row['status'] ?? ''),
            ];
        }

        return $items;
    }

    private function loadQuickSystem(LinkGenerator $links): array
    {
        $items = [];
        $routes = [
            ['Domů', 'home', $links->home(), 'Úvodní stránka webu'],
            ['Administrace', 'admin', $links->admin(), 'Přihlášená sekce pro správu obsahu'],
            ['Přihlášení', 'login', $links->login(), 'Formulář pro přihlášení'],
            ['Registrace', 'register', $links->register(), 'Formulář pro vytvoření účtu'],
            ['Odhlášení', 'logout', $links->logout(), 'Odkaz k odhlášení uživatele'],
            ['Zapomenuté heslo', 'lost', $links->lost(), 'Stránka pro obnovu hesla'],
            ['Vyhledávání', 'search', $links->search(), 'Výsledky vyhledávání'],
        ];

        foreach ($routes as [$title, $route, $url, $description]) {
            $items[] = [
                'title' => $title,
                'slug' => $route,
                'url' => $url,
                'description' => $description,
                'link_type' => 'route',
                'link_reference' => $route,
                'type' => 'route',
            ];
        }

        return $items;
    }

    private function loadQuickCategories(LinkGenerator $links): array
    {
        $rows = DB::query()
            ->table('terms')
            ->select(['id', 'name', 'slug'])
            ->where('type', '=', 'category')
            ->orderBy('name', 'ASC')
            ->limit(50)
            ->get() ?? [];

        $items = [];
        foreach ($rows as $row) {
            $name = trim((string)($row['name'] ?? ''));
            $slug = trim((string)($row['slug'] ?? ''));
            $id = (int)($row['id'] ?? 0);
            if ($name === '' || $slug === '') {
                continue;
            }
            if ($id <= 0) {
                continue;
            }

            $items[] = [
                'id' => $id,
                'title' => $name,
                'slug' => $slug,
                'url' => $links->category($slug),
                'type' => 'category',
                'link_type' => 'category',
                'link_reference' => (string)$id,
            ];
        }

        return $items;
    }

    private function createMenu(): void
    {
        $this->assertCsrf();
        if (!$this->tablesReady()) {
            $this->flash('danger', 'Tabulky navigace nejsou k dispozici.');
            $this->redirectTo(null);
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $slugInput = trim((string)($_POST['slug'] ?? ''));
        $location = $this->sanitizeLocation((string)($_POST['location'] ?? 'primary'));
        $description = trim((string)($_POST['description'] ?? ''));

        if ($name === '') {
            $this->flash('danger', 'Název menu je povinný.');
            $this->redirectTo(null);
        }

        $existingLocation = $this->menuByLocation($location);
        if ($existingLocation) {
            $label = $this->humanizeLocation($location);
            $nameExisting = (string)($existingLocation['name'] ?? '');
            $this->flash('danger', sprintf('Umístění „%s“ již používá menu „%s“. Nejprve změňte nebo odeberte existující menu.', $label, $nameExisting));
            $this->redirectTo((int)($existingLocation['id'] ?? 0));
        }

        $slugBase = $slugInput !== '' ? $slugInput : $name;
        $slug = $this->sanitizeSlug($slugBase);
        $slug = $this->ensureUniqueSlug($slug, null);

        $menuId = (int) DB::query()
            ->table('navigation_menus')
            ->insert([
                'slug' => $slug,
                'name' => $name,
                'location' => $location,
                'description' => $description,
                'created_at' => DateTimeFactory::nowString(),
                'updated_at' => DateTimeFactory::nowString(),
            ])
            ->insertGetId();

        $this->flash('success', 'Menu bylo vytvořeno.');
        $this->redirectTo($menuId);
    }

    private function updateMenu(): void
    {
        $this->assertCsrf();
        $menuId = (int)($_POST['id'] ?? 0);
        if ($menuId <= 0) {
            $this->flash('danger', 'Chybí ID menu.');
            $this->redirectTo(null);
        }
        $menu = $this->findMenu($menuId);
        if (!$menu) {
            $this->flash('danger', 'Menu nebylo nalezeno.');
            $this->redirectTo(null);
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $slugInput = trim((string)($_POST['slug'] ?? ''));
        $location = $this->sanitizeLocation((string)($_POST['location'] ?? 'primary'));
        $description = trim((string)($_POST['description'] ?? ''));

        if ($name === '') {
            $this->flash('danger', 'Název menu je povinný.');
            $this->redirectTo($menuId);
        }

        $existingLocation = $this->menuByLocation($location, $menuId);
        if ($existingLocation) {
            $label = $this->humanizeLocation($location);
            $nameExisting = (string)($existingLocation['name'] ?? '');
            $this->flash('danger', sprintf('Umístění „%s“ již používá menu „%s“. Nejprve uvolněte danou lokaci.', $label, $nameExisting));
            $this->redirectTo($menuId);
        }

        $slugBase = $slugInput !== '' ? $slugInput : $name;
        $slug = $this->sanitizeSlug($slugBase);
        $slug = $this->ensureUniqueSlug($slug, $menuId);

        DB::query()
            ->table('navigation_menus')
            ->update([
                'name' => $name,
                'slug' => $slug,
                'location' => $location,
                'description' => $description,
                'updated_at' => DateTimeFactory::nowString(),
            ])
            ->where('id', '=', $menuId)
            ->execute();

        $this->flash('success', 'Menu bylo upraveno.');
        $this->redirectTo($menuId);
    }

    private function deleteMenu(): void
    {
        $this->assertCsrf();
        $menuId = (int)($_POST['id'] ?? 0);
        if ($menuId <= 0) {
            $this->flash('danger', 'Chybí ID menu.');
            $this->redirectTo(null);
        }
        $menu = $this->findMenu($menuId);
        if (!$menu) {
            $this->flash('danger', 'Menu nebylo nalezeno.');
            $this->redirectTo(null);
        }

        DB::query()->table('navigation_items')->where('menu_id', '=', $menuId)->delete()->execute();
        DB::query()->table('navigation_menus')->where('id', '=', $menuId)->delete()->execute();

        $this->flash('success', 'Menu bylo smazáno.');
        $this->redirectTo(null);
    }

    private function createItem(): void
    {
        $this->assertCsrf();
        $menuId = (int)($_POST['menu_id'] ?? 0);
        if ($menuId <= 0) {
            $this->flash('danger', 'Chybí ID menu.');
            $this->redirectTo(null);
        }
        $menu = $this->findMenu($menuId);
        if (!$menu) {
            $this->flash('danger', 'Menu nebylo nalezeno.');
            $this->redirectTo(null);
        }

        $title = trim((string)($_POST['title'] ?? ''));
        $urlInput = trim((string)($_POST['url'] ?? ''));
        $linkTypeInput = (string)($_POST['link_type'] ?? 'custom');
        $linkReferenceInput = trim((string)($_POST['link_reference'] ?? ''));
        $target = $this->sanitizeTarget((string)($_POST['target'] ?? '_self'));
        $cssClass = trim((string)($_POST['css_class'] ?? ''));
        $parentId = (int)($_POST['parent_id'] ?? 0);
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if ($title === '') {
            $this->flash('danger', 'Název položky je povinný.');
            $this->redirectTo($menuId);
        }

        $linkData = $this->prepareLinkData($linkTypeInput, $linkReferenceInput, $urlInput);

        if ($linkData['type'] === 'custom' && $linkData['url'] === '') {
            $this->flash('danger', 'Pro vlastní odkaz musíte vyplnit URL adresu.');
            $this->redirectTo($menuId);
        }

        if ($linkData['type'] !== 'custom' && !$linkData['valid']) {
            $messages = $this->linkStatusMessages();
            $reason = $linkData['reason'] ?? 'missing';
            $message = $messages[$reason] ?? 'Vybraný obsah není možné použít v navigaci.';
            $this->flash('danger', $message);
            $this->redirectTo($menuId);
        }

        $linkType = $linkData['type'];
        $linkReference = $linkData['reference'];
        $url = $linkData['url'];

        $parent = null;
        if ($parentId > 0) {
            $parent = DB::query()
                ->table('navigation_items')
                ->select(['id', 'menu_id'])
                ->where('id', '=', $parentId)
                ->first();
            if (!$parent || (int)$parent['menu_id'] !== $menuId) {
                $parentId = 0;
            }
        }

        $itemId = (int) DB::query()
            ->table('navigation_items')
            ->insert([
                'menu_id' => $menuId,
                'parent_id' => $parentId > 0 ? $parentId : null,
                'title' => $title,
                'link_type' => $linkType,
                'link_reference' => $linkReference !== '' ? $linkReference : null,
                'url' => $url,
                'target' => $target,
                'css_class' => $cssClass !== '' ? $cssClass : null,
                'sort_order' => $sortOrder,
                'created_at' => DateTimeFactory::nowString(),
                'updated_at' => DateTimeFactory::nowString(),
            ])
            ->insertGetId();

        $this->flash('success', 'Položka byla přidána.');
        $this->redirectTo($menuId, ['item_id' => $itemId]);
    }

    private function updateItem(): void
    {
        $this->assertCsrf();
        $itemId = (int)($_POST['id'] ?? 0);
        $menuId = (int)($_POST['menu_id'] ?? 0);
        if ($itemId <= 0 || $menuId <= 0) {
            $this->flash('danger', 'Chybí údaje položky.');
            $this->redirectTo(null);
        }

        $item = DB::query()
            ->table('navigation_items')
            ->select(['id', 'menu_id', 'parent_id'])
            ->where('id', '=', $itemId)
            ->first();
        if (!$item || (int)$item['menu_id'] !== $menuId) {
            $this->flash('danger', 'Položka nebyla nalezena.');
            $this->redirectTo(null);
        }

        $title = trim((string)($_POST['title'] ?? ''));
        $urlInput = trim((string)($_POST['url'] ?? ''));
        $linkTypeInput = (string)($_POST['link_type'] ?? 'custom');
        $linkReferenceInput = trim((string)($_POST['link_reference'] ?? ''));
        $target = $this->sanitizeTarget((string)($_POST['target'] ?? '_self'));
        $cssClass = trim((string)($_POST['css_class'] ?? ''));
        $parentId = (int)($_POST['parent_id'] ?? 0);
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if ($title === '') {
            $this->flash('danger', 'Název položky je povinný.');
            $this->redirectTo($menuId, ['item_id' => $itemId]);
        }

        $linkData = $this->prepareLinkData($linkTypeInput, $linkReferenceInput, $urlInput);

        if ($linkData['type'] === 'custom' && $linkData['url'] === '') {
            $this->flash('danger', 'Pro vlastní odkaz musíte vyplnit URL adresu.');
            $this->redirectTo($menuId, ['item_id' => $itemId]);
        }

        if ($linkData['type'] !== 'custom' && !$linkData['valid']) {
            $messages = $this->linkStatusMessages();
            $reason = $linkData['reason'] ?? 'missing';
            $message = $messages[$reason] ?? 'Vybraný obsah není možné použít v navigaci.';
            $this->flash('danger', $message);
            $this->redirectTo($menuId, ['item_id' => $itemId]);
        }

        $linkType = $linkData['type'];
        $linkReference = $linkData['reference'];
        $url = $linkData['url'];

        $itemsRaw = $this->allItemsForMenu($menuId);
        $invalidParents = $this->descendantIdsFromList($itemsRaw, $itemId);
        $invalidParents[] = $itemId;

        if ($parentId > 0) {
            if (in_array($parentId, $invalidParents, true)) {
                $parentId = 0;
            } else {
                $parent = DB::query()
                    ->table('navigation_items')
                    ->select(['id', 'menu_id'])
                    ->where('id', '=', $parentId)
                    ->first();
                if (!$parent || (int)$parent['menu_id'] !== $menuId) {
                    $parentId = 0;
                }
            }
        }

        DB::query()
            ->table('navigation_items')
            ->update([
                'title' => $title,
                'link_type' => $linkType,
                'link_reference' => $linkReference !== '' ? $linkReference : null,
                'url' => $url,
                'target' => $target,
                'css_class' => $cssClass !== '' ? $cssClass : null,
                'parent_id' => $parentId > 0 ? $parentId : null,
                'sort_order' => $sortOrder,
                'updated_at' => DateTimeFactory::nowString(),
            ])
            ->where('id', '=', $itemId)
            ->execute();

        $this->flash('success', 'Položka byla upravena.');
        $this->redirectTo($menuId, ['item_id' => $itemId]);
    }

    private function deleteItem(): void
    {
        $this->assertCsrf();
        $itemId = (int)($_POST['id'] ?? 0);
        $menuId = (int)($_POST['menu_id'] ?? 0);
        if ($itemId <= 0 || $menuId <= 0) {
            $this->flash('danger', 'Chybí údaje položky.');
            $this->redirectTo(null);
        }

        $item = DB::query()
            ->table('navigation_items')
            ->select(['id', 'menu_id'])
            ->where('id', '=', $itemId)
            ->first();
        if (!$item || (int)$item['menu_id'] !== $menuId) {
            $this->flash('danger', 'Položka nebyla nalezena.');
            $this->redirectTo(null);
        }

        DB::query()
            ->table('navigation_items')
            ->update(['parent_id' => null])
            ->where('parent_id', '=', $itemId)
            ->execute();

        DB::query()
            ->table('navigation_items')
            ->where('id', '=', $itemId)
            ->delete()
            ->execute();

        $this->flash('success', 'Položka byla odstraněna.');
        $this->redirectTo($menuId);
    }
}
