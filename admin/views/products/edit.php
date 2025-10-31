<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array<string,mixed>|null $product */
/** @var array<int,array<string,mixed>> $categories */
/** @var array<int> $selectedCategories */
/** @var array<int,array<string,mixed>> $variants */
/** @var array<int,array<int,string|null>> $variantAttributes */
/** @var array<int,array<string,mixed>> $attributes */
/** @var string $csrf */

$this->render('parts/layouts/base', compact('pageTitle', 'nav', 'currentUser', 'flash'), function () use ($product, $categories, $selectedCategories, $variants, $variantAttributes, $attributes, $csrf) {
    $isEdit = $product !== null && isset($product['id']);
    $action = $isEdit ? 'admin.php?r=products&a=edit&id=' . (int)$product['id'] : 'admin.php?r=products&a=create';
    $h = fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

    $categoryOptions = array_map(static fn(array $category): array => [
        'id' => (int)($category['id'] ?? 0),
        'name' => (string)($category['name'] ?? ''),
    ], $categories);

    $variantRows = $variants;
    $variantRows[] = [
        'id' => null,
        'name' => '',
        'sku' => '',
        'price' => $product['price'] ?? 0,
        'compare_at_price' => $product['price'] ?? null,
        'inventory_quantity' => 0,
        'track_inventory' => 1,
    ];
?>
  <form method="post" action="<?= $h($action) ?>" novalidate>
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">

    <div class="row g-3 mb-4">
      <div class="col-lg-8">
        <div class="card">
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label" for="product-name">Název produktu</label>
              <input class="form-control" id="product-name" name="name" type="text" required value="<?= $h((string)($product['name'] ?? '')) ?>">
            </div>
            <div class="mb-3">
              <label class="form-label" for="product-slug">Slug</label>
              <input class="form-control" id="product-slug" name="slug" type="text" value="<?= $h((string)($product['slug'] ?? '')) ?>" placeholder="bude vygenerován automaticky">
            </div>
            <div class="mb-3">
              <label class="form-label" for="product-description">Popis</label>
              <textarea class="form-control" id="product-description" name="description" rows="6"><?= $h((string)($product['description'] ?? '')) ?></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label" for="product-short-description">Krátký popis</label>
              <textarea class="form-control" id="product-short-description" name="short_description" rows="3"><?= $h((string)($product['short_description'] ?? '')) ?></textarea>
            </div>
          </div>
        </div>

        <div class="card mt-4">
          <div class="card-header">
            <h2 class="card-title h5 mb-0">Varianty</h2>
          </div>
          <div class="card-body">
            <?php foreach ($variantRows as $index => $variant): ?>
              <div class="border rounded p-3 mb-3">
                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label" for="variant-name-<?= $index ?>">Název varianty</label>
                    <input class="form-control" id="variant-name-<?= $index ?>" name="variants[<?= $index ?>][name]" type="text" value="<?= $h((string)($variant['name'] ?? '')) ?>">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label" for="variant-sku-<?= $index ?>">SKU</label>
                    <input class="form-control" id="variant-sku-<?= $index ?>" name="variants[<?= $index ?>][sku]" type="text" value="<?= $h((string)($variant['sku'] ?? '')) ?>">
                  </div>
                  <div class="col-md-2">
                    <label class="form-label" for="variant-price-<?= $index ?>">Cena</label>
                    <input class="form-control" id="variant-price-<?= $index ?>" name="variants[<?= $index ?>][price]" type="text" value="<?= $h((string)($variant['price'] ?? '0')) ?>">
                  </div>
                  <div class="col-md-2">
                    <label class="form-label" for="variant-compare-<?= $index ?>">Původní cena</label>
                    <input class="form-control" id="variant-compare-<?= $index ?>" name="variants[<?= $index ?>][compare_at_price]" type="text" value="<?= $h((string)($variant['compare_at_price'] ?? '')) ?>">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label" for="variant-stock-<?= $index ?>">Sklad</label>
                    <input class="form-control" id="variant-stock-<?= $index ?>" name="variants[<?= $index ?>][inventory_quantity]" type="number" value="<?= (int)($variant['inventory_quantity'] ?? 0) ?>">
                  </div>
                  <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" value="1" id="variant-track-<?= $index ?>" name="variants[<?= $index ?>][track_inventory]" <?= !empty($variant['track_inventory']) ? 'checked' : '' ?>>
                      <label class="form-check-label" for="variant-track-<?= $index ?>">Sledovat zásoby</label>
                    </div>
                  </div>
                </div>

                <?php if ($attributes !== []): ?>
                  <div class="row g-3 mt-2">
                    <?php foreach ($attributes as $attribute): ?>
                      <?php $attrId = (int)($attribute['id'] ?? 0); ?>
                      <div class="col-md-3">
                        <label class="form-label" for="variant-attr-<?= $index ?>-<?= $attrId ?>"><?= $h((string)($attribute['name'] ?? 'Vlastnost')) ?></label>
                        <input class="form-control" id="variant-attr-<?= $index ?>-<?= $attrId ?>" type="text" name="variants[<?= $index ?>][attributes][<?= $attrId ?>]" value="<?= $h((string)($variantAttributes[$variant['id'] ?? 0][$attrId] ?? '')) ?>">
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

                <?php if (!empty($variant['id'])): ?>
                  <input type="hidden" name="variants[<?= $index ?>][id]" value="<?= (int)$variant['id'] ?>">
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
            <p class="text-muted small mb-0">Novou variantu přidejte vyplněním prázdného formuláře. Ponecháním polí prázdných se varianta nevytvoří.</p>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card mb-4">
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label" for="product-status">Stav</label>
              <select class="form-select" id="product-status" name="status">
                <?php foreach (['draft' => 'Koncept', 'active' => 'Aktivní', 'archived' => 'Archivováno'] as $value => $label): ?>
                  <option value="<?= $h($value) ?>" <?= ($product['status'] ?? 'draft') === $value ? 'selected' : '' ?>><?= $h($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="row g-2">
              <div class="col-8">
                <label class="form-label" for="product-price">Základní cena</label>
                <input class="form-control" id="product-price" name="price" type="text" value="<?= $h((string)($product['price'] ?? '0')) ?>">
              </div>
              <div class="col-4">
                <label class="form-label" for="product-currency">Měna</label>
                <input class="form-control" id="product-currency" name="currency" type="text" maxlength="3" value="<?= $h((string)($product['currency'] ?? 'USD')) ?>">
              </div>
            </div>
            <div class="mt-3">
              <label class="form-label" for="product-tax">Daňová třída</label>
              <input class="form-control" id="product-tax" name="tax_class" type="text" value="<?= $h((string)($product['tax_class'] ?? '')) ?>">
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-body">
            <h2 class="h6">Kategorie</h2>
            <?php if ($categoryOptions === []): ?>
              <p class="text-muted">Nejprve vytvořte kategorie v sekci „Kategorie“.</p>
            <?php else: ?>
              <?php foreach ($categoryOptions as $category): ?>
                <?php $checked = in_array($category['id'], $selectedCategories, true); ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="category-<?= $category['id'] ?>" name="categories[]" value="<?= $category['id'] ?>" <?= $checked ? 'checked' : '' ?>>
                  <label class="form-check-label" for="category-<?= $category['id'] ?>"><?= $h($category['name']) ?></label>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-between">
      <a class="btn btn-outline-secondary" href="admin.php?r=products">Zpět na seznam</a>
      <button class="btn btn-primary" type="submit">Uložit</button>
    </div>
  </form>
<?php
});
