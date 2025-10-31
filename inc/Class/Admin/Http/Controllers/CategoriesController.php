<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Controllers;

use Cms\Admin\Utils\AdminNavigation;
use Cms\Admin\Utils\Slugger;
use Cms\Models\Repositories\CategoryRepository;
use Core\Database\Init as DB;

final class CategoriesController extends BaseAdminController
{
    public function handle(string $action): void
    {
        switch ($action) {
            case 'create':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $this->store();
                } else {
                    $this->form();
                }
                return;

            case 'edit':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $this->update();
                } else {
                    $this->form();
                }
                return;

            case 'delete':
                $this->delete();
                return;

            default:
                $this->index();
                return;
        }
    }

    private function index(): void
    {
        $filters = [
            'q' => (string)($_GET['q'] ?? ''),
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;

        $query = DB::query()->table('categories', 'c')
            ->select(['c.id', 'c.name', 'c.slug', 'c.parent_id', 'c.sort_order', 'c.created_at'])
            ->orderBy('c.sort_order', 'ASC')
            ->orderBy('c.name', 'ASC');

        if ($filters['q'] !== '') {
            $search = '%' . $filters['q'] . '%';
            $query->where(function ($w) use ($search) {
                $w->whereLike('c.name', $search)->orWhere('c.slug', 'LIKE', $search);
            });
        }

        $paginated = $query->paginate($page, $perPage);
        $items = $paginated['items'] ?? [];

        $parentNames = $this->loadParentNames($items);
        foreach ($items as &$item) {
            $parentId = isset($item['parent_id']) ? (int)$item['parent_id'] : 0;
            $item['parent_name'] = $parentNames[$parentId] ?? null;
        }
        unset($item);

        $this->renderAdmin('categories/index', [
            'pageTitle'  => 'Kategorie produktů',
            'nav'        => AdminNavigation::build('categories'),
            'filters'    => $filters,
            'items'      => $items,
            'pagination' => $this->paginationData($paginated, $page, $perPage),
            'buildUrl'   => $this->listingUrlBuilder(['r' => 'categories', 'q' => $filters['q']]),
        ]);
    }

    private function form(): void
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $repo = new CategoryRepository();
        $category = $id > 0 ? $repo->find($id) : null;

        if ($id > 0 && $category === null) {
            $this->redirect('admin.php?r=categories', 'danger', 'Kategorie nebyla nalezena.');
        }

        $allCategories = array_map(static fn($model) => $model->toArray(), $repo->all());

        $this->renderAdmin('categories/edit', [
            'pageTitle' => $category ? 'Upravit kategorii' : 'Nová kategorie',
            'nav'       => AdminNavigation::build('categories'),
            'category'  => $category?->toArray(),
            'categories'=> $allCategories,
        ]);
    }

    private function store(): void
    {
        $this->assertCsrf();
        $input = $this->validateInput();
        if ($input['errors'] !== []) {
            $this->respondValidationErrors($input['errors'], 'Vyplňte povinné údaje kategorie.', 'admin.php?r=categories&a=create');
        }

        $repo = new CategoryRepository();
        $slug = Slugger::uniqueInCategories($input['slug'] !== '' ? $input['slug'] : $input['name']);
        $payload = [
            'name' => $input['name'],
            'slug' => $slug,
            'description' => $input['description'],
            'parent_id' => $input['parent_id'] ?: null,
            'sort_order' => $input['sort_order'],
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];

        $category = $repo->create($payload);

        $this->redirect('admin.php?r=categories&a=edit&id=' . (int)$category->id, 'success', 'Kategorie byla vytvořena.');
    }

    private function update(): void
    {
        $this->assertCsrf();
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            $this->redirect('admin.php?r=categories', 'danger', 'Neplatná kategorie.');
        }

        $repo = new CategoryRepository();
        $existing = $repo->find($id);
        if ($existing === null) {
            $this->redirect('admin.php?r=categories', 'danger', 'Kategorie nebyla nalezena.');
        }

        $input = $this->validateInput();
        if ($input['errors'] !== []) {
            $this->respondValidationErrors($input['errors'], 'Vyplňte povinné údaje kategorie.', 'admin.php?r=categories&a=edit&id=' . $id);
        }

        $slug = Slugger::uniqueInCategories($input['slug'] !== '' ? $input['slug'] : $input['name'], $id);
        $payload = [
            'name' => $input['name'],
            'slug' => $slug,
            'description' => $input['description'],
            'parent_id' => $input['parent_id'] ?: null,
            'sort_order' => $input['sort_order'],
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];

        $repo->update($id, $payload);

        $this->redirect('admin.php?r=categories&a=edit&id=' . $id, 'success', 'Kategorie byla aktualizována.');
    }

    private function delete(): void
    {
        $this->assertCsrf();
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            $this->redirect('admin.php?r=categories', 'danger', 'Neplatná kategorie.');
        }

        $repo = new CategoryRepository();
        $category = $repo->find($id);
        if ($category === null) {
            $this->redirect('admin.php?r=categories', 'danger', 'Kategorie nebyla nalezena.');
        }

        $repo->delete($id);

        if ($this->isAjax()) {
            $this->jsonSuccess(['message' => 'Kategorie byla odstraněna.']);
        }

        $this->redirect('admin.php?r=categories', 'success', 'Kategorie byla odstraněna.');
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<int,string>
     */
    private function loadParentNames(array $items): array
    {
        $parentIds = [];
        foreach ($items as $item) {
            $parentId = isset($item['parent_id']) ? (int)$item['parent_id'] : 0;
            if ($parentId > 0) {
                $parentIds[$parentId] = $parentId;
            }
        }

        if ($parentIds === []) {
            return [];
        }

        $repo = new CategoryRepository();
        $names = [];
        foreach ($repo->all() as $category) {
            $id = (int)$category->id;
            if (!isset($parentIds[$id])) {
                continue;
            }
            $names[$id] = (string)$category->name;
        }

        return $names;
    }

    /**
     * @return array{name:string,slug:string,description:string,parent_id:int,sort_order:int,errors:array<string,string>}
     */
    private function validateInput(): array
    {
        $name = trim((string)($_POST['name'] ?? ''));
        $slug = trim((string)($_POST['slug'] ?? ''));
        $description = (string)($_POST['description'] ?? '');
        $parentId = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;
        $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Název je povinný.';
        }
        if ($parentId < 0) {
            $parentId = 0;
        }

        return [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'parent_id' => $parentId,
            'sort_order' => $sortOrder,
            'errors' => $errors,
        ];
    }

    /**
     * @param array<string,string> $errors
     */
    private function respondValidationErrors(array $errors, string $fallbackMessage, string $redirectUrl): never
    {
        if ($this->isAjax()) {
            $this->jsonError($errors, status: 422);
        }

        $this->flash('danger', $fallbackMessage);
        $this->redirect($redirectUrl);
    }
}

