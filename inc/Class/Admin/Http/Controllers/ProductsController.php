<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Controllers;

use Cms\Admin\Utils\AdminNavigation;
use Cms\Admin\Utils\DateTimeFactory;
use Cms\Admin\Utils\Slugger;
use Cms\Models\Repositories\CategoryRepository;
use Cms\Models\Repositories\ProductAttributeRepository;
use Cms\Models\Repositories\ProductRepository;
use Cms\Models\Repositories\ProductVariantRepository;
use Core\Database\Init as DB;

final class ProductsController extends BaseAdminController
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
            'status' => (string)($_GET['status'] ?? ''),
            'q'      => (string)($_GET['q'] ?? ''),
        ];

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;

        $query = DB::query()->table('products', 'p')
            ->select(['p.id', 'p.name', 'p.slug', 'p.status', 'p.price', 'p.currency', 'p.created_at', 'p.updated_at'])
            ->orderBy('p.created_at', 'DESC');

        if ($filters['status'] !== '') {
            $query->where('p.status', '=', $filters['status']);
        }

        if ($filters['q'] !== '') {
            $search = '%' . $filters['q'] . '%';
            $query->where(function ($w) use ($search) {
                $w->whereLike('p.name', $search)->orWhere('p.slug', 'LIKE', $search);
            });
        }

        $paginated = $query->paginate($page, $perPage);
        $items = $paginated['items'] ?? [];

        $productIds = array_map(static fn(array $row) => (int)$row['id'], $items);
        $categories = $this->categoriesForProducts($productIds);

        $dateFactory = new DateTimeFactory();
        foreach ($items as &$item) {
            $createdRaw = isset($item['created_at']) ? (string)$item['created_at'] : null;
            $updatedRaw = isset($item['updated_at']) ? (string)$item['updated_at'] : null;
            $item['created_at_display'] = $createdRaw ? $dateFactory->fromStorage($createdRaw)?->format('d.m.Y H:i') : '';
            $item['updated_at_display'] = $updatedRaw ? $dateFactory->fromStorage($updatedRaw)?->format('d.m.Y H:i') : '';
            $item['categories'] = $categories[(int)$item['id']] ?? [];
        }
        unset($item);

        $this->renderAdmin('products/index', [
            'pageTitle'  => 'Produkty',
            'nav'        => AdminNavigation::build('products'),
            'items'      => $items,
            'filters'    => $filters,
            'pagination' => $this->paginationData($paginated, $page, $perPage),
            'buildUrl'   => $this->listingUrlBuilder([
                'r'      => 'products',
                'status' => $filters['status'],
                'q'      => $filters['q'],
            ]),
        ]);
    }

    private function form(): void
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $repo = new ProductRepository();
        $product = $id > 0 ? $repo->find($id) : null;

        if ($id > 0 && $product === null) {
            $this->redirect('admin.php?r=products', 'danger', 'Produkt nebyl nalezen.');
        }

        $categoryRepo = new CategoryRepository();
        $allCategories = $categoryRepo->all();
        $selectedCategories = [];
        if ($product !== null) {
            $assigned = $repo->categories((int)$product->id);
            $selectedCategories = array_map(static fn($category) => (int)$category->id, $assigned);
        }

        $variantRepo = new ProductVariantRepository();
        $variants = $product !== null ? $variantRepo->forProduct((int)$product->id) : [];
        $attributes = (new ProductAttributeRepository())->all();

        $variantAttributes = [];
        if ($product !== null) {
            foreach ($variants as $variant) {
                $values = $variantRepo->attributes((int)$variant->id);
                $variantAttributes[(int)$variant->id] = [];
                foreach ($values as $value) {
                    $attributeId = isset($value['attribute']->id) ? (int)$value['attribute']->id : 0;
                    if ($attributeId > 0) {
                        $variantAttributes[(int)$variant->id][$attributeId] = $value['value'];
                    }
                }
            }
        }

        $this->renderAdmin('products/edit', [
            'pageTitle'         => $product ? 'Upravit produkt' : 'Nový produkt',
            'nav'               => AdminNavigation::build('products'),
            'product'           => $product?->toArray(),
            'categories'        => array_map(static fn($category) => $category->toArray(), $allCategories),
            'selectedCategories'=> $selectedCategories,
            'variants'          => array_map(static fn($variant) => $variant->toArray(), $variants),
            'variantAttributes' => $variantAttributes,
            'attributes'        => array_map(static fn($attribute) => $attribute->toArray(), $attributes),
        ]);
    }

    private function store(): void
    {
        $this->assertCsrf();

        $input = $this->validateProductInput();
        if ($input['errors'] !== []) {
            $this->respondValidationErrors($input['errors'], 'Vyplňte povinné údaje produktu.', 'admin.php?r=products&a=create');
        }

        $repo = new ProductRepository();

        $slug = Slugger::uniqueInProducts($input['slug'] !== '' ? $input['slug'] : $input['name']);
        $data = [
            'name' => $input['name'],
            'slug' => $slug,
            'description' => $input['description'],
            'short_description' => $input['short_description'],
            'status' => $input['status'],
            'price' => $input['price'],
            'currency' => $input['currency'],
            'tax_class' => $input['tax_class'] !== '' ? $input['tax_class'] : null,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];
        if ($input['status'] === 'active') {
            $data['published_at'] = gmdate('Y-m-d H:i:s');
        }

        $product = $repo->create($data);

        $this->syncCategories((int)$product->id, $input['categories']);
        $this->syncVariants((int)$product->id, $input['variants'], $input['currency']);

        $this->redirect('admin.php?r=products&a=edit&id=' . (int)$product->id, 'success', 'Produkt byl vytvořen.');
    }

    private function update(): void
    {
        $this->assertCsrf();

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            $this->redirect('admin.php?r=products', 'danger', 'Neplatný produkt.');
        }

        $repo = new ProductRepository();
        $product = $repo->find($id);
        if ($product === null) {
            $this->redirect('admin.php?r=products', 'danger', 'Produkt nebyl nalezen.');
        }

        $input = $this->validateProductInput();
        if ($input['errors'] !== []) {
            $this->respondValidationErrors($input['errors'], 'Vyplňte povinné údaje produktu.', 'admin.php?r=products&a=edit&id=' . $id);
        }

        $slug = Slugger::uniqueInProducts($input['slug'] !== '' ? $input['slug'] : $input['name'], $id);
        $data = [
            'name' => $input['name'],
            'slug' => $slug,
            'description' => $input['description'],
            'short_description' => $input['short_description'],
            'status' => $input['status'],
            'price' => $input['price'],
            'currency' => $input['currency'],
            'tax_class' => $input['tax_class'] !== '' ? $input['tax_class'] : null,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];
        if ($input['status'] === 'active' && empty($product->published_at)) {
            $data['published_at'] = gmdate('Y-m-d H:i:s');
        }

        $repo->update($id, $data);

        $this->syncCategories($id, $input['categories']);
        $this->syncVariants($id, $input['variants'], $input['currency']);

        $this->redirect('admin.php?r=products&a=edit&id=' . $id, 'success', 'Produkt byl aktualizován.');
    }

    private function delete(): void
    {
        $this->assertCsrf();

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            $this->redirect('admin.php?r=products', 'danger', 'Neplatný produkt.');
        }

        $repo = new ProductRepository();
        $product = $repo->find($id);
        if ($product === null) {
            $this->redirect('admin.php?r=products', 'danger', 'Produkt nebyl nalezen.');
        }

        $repo->delete($id);

        if ($this->isAjax()) {
            $this->jsonSuccess(['message' => 'Produkt byl odstraněn.']);
        }

        $this->redirect('admin.php?r=products', 'success', 'Produkt byl odstraněn.');
    }

    /**
     * @param list<int> $productIds
     * @return array<int,list<string>>
     */
    private function categoriesForProducts(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $sql = 'SELECT cp.product_id, c.name'
            . ' FROM category_product cp'
            . ' INNER JOIN categories c ON c.id = cp.category_id'
            . ' WHERE cp.product_id IN (' . implode(',', array_fill(0, count($productIds), '?')) . ')'
            . ' ORDER BY c.name';

        $rows = db_fetch_all($sql, $productIds);
        $grouped = [];
        foreach ($rows as $row) {
            $productId = (int)($row['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            if (!isset($grouped[$productId])) {
                $grouped[$productId] = [];
            }
            $grouped[$productId][] = (string)($row['name'] ?? '');
        }

        return $grouped;
    }

    /**
     * @return array{name:string,slug:string,description:string,short_description:string,status:string,price:float,currency:string,tax_class:string,categories:list<int>,variants:array<int,array<string,mixed>>,errors:array<string,string|array>}
     */
    private function validateProductInput(): array
    {
        $name = trim((string)($_POST['name'] ?? ''));
        $slug = trim((string)($_POST['slug'] ?? ''));
        $description = (string)($_POST['description'] ?? '');
        $shortDescription = (string)($_POST['short_description'] ?? '');
        $status = (string)($_POST['status'] ?? 'draft');
        $currency = strtoupper(substr(trim((string)($_POST['currency'] ?? 'USD')), 0, 3));
        if ($currency === '') {
            $currency = 'USD';
        }
        $taxClass = trim((string)($_POST['tax_class'] ?? ''));

        $priceRaw = (string)($_POST['price'] ?? '0');
        $price = (float)str_replace(',', '.', $priceRaw);

        $categories = $_POST['categories'] ?? [];
        if (!is_array($categories)) {
            $categories = [];
        }
        $categories = array_values(array_filter(array_map(static fn($value) => (int)$value, $categories), static fn($id) => $id > 0));

        $variants = $_POST['variants'] ?? [];
        if (!is_array($variants)) {
            $variants = [];
        }

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Název je povinný.';
        }
        if (!in_array($status, ['draft', 'active', 'archived'], true)) {
            $status = 'draft';
        }
        if ($price < 0) {
            $errors['price'] = 'Cena nesmí být záporná.';
        }

        return [
            'name'              => $name,
            'slug'              => $slug,
            'description'       => $description,
            'short_description' => $shortDescription,
            'status'            => $status,
            'price'             => round($price, 2),
            'currency'          => $currency,
            'tax_class'         => $taxClass,
            'categories'        => $categories,
            'variants'          => $variants,
            'errors'            => $errors,
        ];
    }

    /**
     * @param list<int> $categoryIds
     */
    private function syncCategories(int $productId, array $categoryIds): void
    {
        $repo = new ProductRepository();
        $repo->syncCategories($productId, $categoryIds);
    }

    /**
     * @param array<int,array<string,mixed>> $variantsInput
     */
    private function syncVariants(int $productId, array $variantsInput, string $currency): void
    {
        $repo = new ProductVariantRepository();
        $existing = [];
        foreach ($repo->forProduct($productId) as $variant) {
            $existing[(int)$variant->id] = $variant;
        }

        $keptIds = [];
        foreach ($variantsInput as $variant) {
            if (!is_array($variant)) {
                continue;
            }
            $id = isset($variant['id']) ? (int)$variant['id'] : 0;
            $name = trim((string)($variant['name'] ?? ''));
            $sku = trim((string)($variant['sku'] ?? ''));
            if ($name === '' || $sku === '') {
                continue;
            }

            $priceRaw = isset($variant['price']) ? (string)$variant['price'] : '0';
            $price = (float)str_replace(',', '.', $priceRaw);
            $compareRaw = isset($variant['compare_at_price']) ? (string)$variant['compare_at_price'] : '';
            $compare = $compareRaw !== '' ? (float)str_replace(',', '.', $compareRaw) : null;
            $inventoryQuantity = isset($variant['inventory_quantity']) ? (int)$variant['inventory_quantity'] : 0;
            $trackInventory = !empty($variant['track_inventory']) ? 1 : 0;

            $payload = [
                'product_id' => $productId,
                'name' => $name,
                'sku' => $sku,
                'price' => round($price, 2),
                'compare_at_price' => $compare,
                'currency' => $currency,
                'inventory_quantity' => $inventoryQuantity,
                'track_inventory' => $trackInventory,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ];

            if ($id > 0 && isset($existing[$id])) {
                $record = $repo->update($id, $payload);
                $variantId = (int)$record->id;
            } else {
                $record = $repo->create($payload);
                $variantId = (int)$record->id;
            }

            $keptIds[] = $variantId;

            $attributeValues = [];
            if (isset($variant['attributes']) && is_array($variant['attributes'])) {
                foreach ($variant['attributes'] as $attributeId => $value) {
                    $attributeId = (int)$attributeId;
                    if ($attributeId <= 0) {
                        continue;
                    }
                    $attributeValues[$attributeId] = $value !== '' ? (string)$value : null;
                }
            }
            $repo->syncAttributes($variantId, $attributeValues);
        }

        foreach ($existing as $variantId => $variant) {
            if (!in_array($variantId, $keptIds, true)) {
                $repo->delete($variantId);
            }
        }

        if ($keptIds === []) {
            $record = $repo->create([
                'product_id' => $productId,
                'name' => 'Default',
                'sku' => 'SKU-' . $productId,
                'price' => 0,
                'currency' => $currency,
                'inventory_quantity' => 0,
                'track_inventory' => 1,
                'sort_order' => 0,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
            $keptIds[] = (int)$record->id;
        }
    }

    /**
     * @param array<string,string>|array<int,mixed> $errors
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

