<?php
declare(strict_types=1);

namespace Cms\Http\Admin;

use Cms\Auth\AuthService;
use Cms\Utils\Slugger;
use Cms\View\ViewEngine;
use Core\Database\Init as DB;
use Cms\Utils\AdminNavigation;
use Cms\Utils\DateTimeFactory;

final class NavigationController
{
    private ViewEngine $view;
    private AuthService $auth;

    public function __construct(string $baseViewsPath)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $this->view = new ViewEngine($baseViewsPath);
        $this->auth = new AuthService();
    }

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


    private function token(): string
    {
        if (empty($_SESSION['csrf_admin'])) {
            $_SESSION['csrf_admin'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf_admin'];
    }

    private function assertCsrf(): void
    {
        $in = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
        if (empty($_SESSION['csrf_admin']) || !hash_equals($_SESSION['csrf_admin'], (string)$in)) {
            http_response_code(419);
            echo 'CSRF token invalid';
            exit;
        }
    }

    private function flash(string $type, string $msg): void
    {
        $_SESSION['_flash'] = ['type' => $type, 'msg' => $msg];
    }

    private function isAjax(): bool
    {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }

        $accept = isset($_SERVER['HTTP_ACCEPT']) ? (string)$_SERVER['HTTP_ACCEPT'] : '';
        return str_contains($accept, 'application/json');
    }

    private function redirect(?int $menuId = null, array $extra = []): void
    {
        $params = array_merge(['r' => 'navigation'], $extra);
        if ($menuId) {
            $params['menu_id'] = $menuId;
        }
        $url = 'admin.php?' . http_build_query($params);
        $flash = $_SESSION['_flash'] ?? null;

        if ($this->isAjax()) {
            $payload = [
                'success'  => $this->flashIndicatesSuccess($flash),
                'redirect' => $url,
            ];
            if (is_array($flash)) {
                $payload['flash'] = [
                    'type' => (string)($flash['type'] ?? ''),
                    'msg'  => (string)($flash['msg'] ?? ''),
                ];
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($payload, JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Location: ' . $url);
        exit;
    }

    private function flashIndicatesSuccess($flash): bool
    {
        if (!is_array($flash)) {
            return true;
        }
        $type = strtolower((string)($flash['type'] ?? ''));
        return $type !== 'danger';
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
            ->select(['id', 'menu_id', 'parent_id', 'title', 'url', 'target', 'css_class', 'sort_order', 'created_at'])
            ->where('menu_id', '=', $menuId)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->get() ?? [];
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
                'url' => (string)$item['url'],
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
            $this->redirect(null);
        }

        $itemsRaw = $menu ? $this->allItemsForMenu((int)$menu['id']) : [];
        $tree = $this->buildTree($itemsRaw);
        $flat = $this->flattenTree($tree);

        $editingItem = null;
        $itemId = (int)($_GET['item_id'] ?? 0);
        if ($itemId > 0 && $menu) {
            $editingItem = DB::query()
                ->table('navigation_items')
                ->select(['id', 'menu_id', 'parent_id', 'title', 'url', 'target', 'css_class', 'sort_order'])
                ->where('id', '=', $itemId)
                ->first() ?: null;
            if (!$editingItem || (int)$editingItem['menu_id'] !== (int)$menu['id']) {
                $editingItem = null;
            }
        }

        $invalidParents = [];
        if ($editingItem) {
            $invalidParents = $this->descendantIdsFromList($itemsRaw, (int)$editingItem['id']);
        }
        $parentOptions = $this->parentOptions($flat, $editingItem ? (int)$editingItem['id'] : null, $invalidParents);

        $this->view->render('navigation/index', [
            'pageTitle' => 'Navigace',
            'nav' => AdminNavigation::build('navigation'),
            'currentUser' => $this->auth->user(),
            'flash' => $_SESSION['_flash'] ?? null,
            'csrf' => $this->token(),
            'tablesReady' => $tablesReady,
            'menus' => $menus,
            'menu' => $menu,
            'menuId' => $menuId,
            'items' => $flat,
            'editingItem' => $editingItem,
            'parentOptions' => $parentOptions,
            'targets' => $this->targetOptions(),
        ]);

        unset($_SESSION['_flash']);
    }

    private function createMenu(): void
    {
        $this->assertCsrf();
        if (!$this->tablesReady()) {
            $this->flash('danger', 'Tabulky navigace nejsou k dispozici.');
            $this->redirect(null);
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $slugInput = trim((string)($_POST['slug'] ?? ''));
        $location = trim((string)($_POST['location'] ?? 'primary'));
        $description = trim((string)($_POST['description'] ?? ''));

        if ($name === '') {
            $this->flash('danger', 'Název menu je povinný.');
            $this->redirect(null);
        }

        if ($location === '') {
            $location = 'primary';
        }
        if (strlen($location) > 64) {
            $location = substr($location, 0, 64);
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
        $this->redirect($menuId);
    }

    private function updateMenu(): void
    {
        $this->assertCsrf();
        $menuId = (int)($_POST['id'] ?? 0);
        if ($menuId <= 0) {
            $this->flash('danger', 'Chybí ID menu.');
            $this->redirect(null);
        }
        $menu = $this->findMenu($menuId);
        if (!$menu) {
            $this->flash('danger', 'Menu nebylo nalezeno.');
            $this->redirect(null);
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $slugInput = trim((string)($_POST['slug'] ?? ''));
        $location = trim((string)($_POST['location'] ?? 'primary'));
        $description = trim((string)($_POST['description'] ?? ''));

        if ($name === '') {
            $this->flash('danger', 'Název menu je povinný.');
            $this->redirect($menuId);
        }

        if ($location === '') {
            $location = 'primary';
        }
        if (strlen($location) > 64) {
            $location = substr($location, 0, 64);
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
        $this->redirect($menuId);
    }

    private function deleteMenu(): void
    {
        $this->assertCsrf();
        $menuId = (int)($_POST['id'] ?? 0);
        if ($menuId <= 0) {
            $this->flash('danger', 'Chybí ID menu.');
            $this->redirect(null);
        }
        $menu = $this->findMenu($menuId);
        if (!$menu) {
            $this->flash('danger', 'Menu nebylo nalezeno.');
            $this->redirect(null);
        }

        DB::query()->table('navigation_items')->where('menu_id', '=', $menuId)->delete()->execute();
        DB::query()->table('navigation_menus')->where('id', '=', $menuId)->delete()->execute();

        $this->flash('success', 'Menu bylo smazáno.');
        $this->redirect(null);
    }

    private function createItem(): void
    {
        $this->assertCsrf();
        $menuId = (int)($_POST['menu_id'] ?? 0);
        if ($menuId <= 0) {
            $this->flash('danger', 'Chybí ID menu.');
            $this->redirect(null);
        }
        $menu = $this->findMenu($menuId);
        if (!$menu) {
            $this->flash('danger', 'Menu nebylo nalezeno.');
            $this->redirect(null);
        }

        $title = trim((string)($_POST['title'] ?? ''));
        $url = trim((string)($_POST['url'] ?? ''));
        $target = $this->sanitizeTarget((string)($_POST['target'] ?? '_self'));
        $cssClass = trim((string)($_POST['css_class'] ?? ''));
        $parentId = (int)($_POST['parent_id'] ?? 0);
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if ($title === '' || $url === '') {
            $this->flash('danger', 'Název i URL položky jsou povinné.');
            $this->redirect($menuId);
        }

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
                'url' => $url,
                'target' => $target,
                'css_class' => $cssClass !== '' ? $cssClass : null,
                'sort_order' => $sortOrder,
                'created_at' => DateTimeFactory::nowString(),
                'updated_at' => DateTimeFactory::nowString(),
            ])
            ->insertGetId();

        $this->flash('success', 'Položka byla přidána.');
        $this->redirect($menuId, ['item_id' => $itemId]);
    }

    private function updateItem(): void
    {
        $this->assertCsrf();
        $itemId = (int)($_POST['id'] ?? 0);
        $menuId = (int)($_POST['menu_id'] ?? 0);
        if ($itemId <= 0 || $menuId <= 0) {
            $this->flash('danger', 'Chybí údaje položky.');
            $this->redirect(null);
        }

        $item = DB::query()
            ->table('navigation_items')
            ->select(['id', 'menu_id', 'parent_id'])
            ->where('id', '=', $itemId)
            ->first();
        if (!$item || (int)$item['menu_id'] !== $menuId) {
            $this->flash('danger', 'Položka nebyla nalezena.');
            $this->redirect(null);
        }

        $title = trim((string)($_POST['title'] ?? ''));
        $url = trim((string)($_POST['url'] ?? ''));
        $target = $this->sanitizeTarget((string)($_POST['target'] ?? '_self'));
        $cssClass = trim((string)($_POST['css_class'] ?? ''));
        $parentId = (int)($_POST['parent_id'] ?? 0);
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if ($title === '' || $url === '') {
            $this->flash('danger', 'Název i URL položky jsou povinné.');
            $this->redirect($menuId, ['item_id' => $itemId]);
        }

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
        $this->redirect($menuId, ['item_id' => $itemId]);
    }

    private function deleteItem(): void
    {
        $this->assertCsrf();
        $itemId = (int)($_POST['id'] ?? 0);
        $menuId = (int)($_POST['menu_id'] ?? 0);
        if ($itemId <= 0 || $menuId <= 0) {
            $this->flash('danger', 'Chybí údaje položky.');
            $this->redirect(null);
        }

        $item = DB::query()
            ->table('navigation_items')
            ->select(['id', 'menu_id'])
            ->where('id', '=', $itemId)
            ->first();
        if (!$item || (int)$item['menu_id'] !== $menuId) {
            $this->flash('danger', 'Položka nebyla nalezena.');
            $this->redirect(null);
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
        $this->redirect($menuId);
    }
}
