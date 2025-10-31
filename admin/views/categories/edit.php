<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array<string,mixed>|null $category */
/** @var array<int,array<string,mixed>> $categories */
/** @var string $csrf */

$this->render('parts/layouts/base', compact('pageTitle', 'nav', 'currentUser', 'flash'), function () use ($category, $categories, $csrf) {
    $isEdit = $category !== null && isset($category['id']);
    $action = $isEdit ? 'admin.php?r=categories&a=edit&id=' . (int)$category['id'] : 'admin.php?r=categories&a=create';
    $h = fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
  <form method="post" action="<?= $h($action) ?>">
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">

    <div class="row g-3">
      <div class="col-lg-8">
        <div class="card">
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label" for="category-name">Název</label>
              <input class="form-control" id="category-name" name="name" type="text" required value="<?= $h((string)($category['name'] ?? '')) ?>">
            </div>
            <div class="mb-3">
              <label class="form-label" for="category-slug">Slug</label>
              <input class="form-control" id="category-slug" name="slug" type="text" value="<?= $h((string)($category['slug'] ?? '')) ?>" placeholder="bude vygenerován automaticky">
            </div>
            <div class="mb-3">
              <label class="form-label" for="category-description">Popis</label>
              <textarea class="form-control" id="category-description" name="description" rows="5"><?= $h((string)($category['description'] ?? '')) ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card">
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label" for="category-parent">Nadřazená kategorie</label>
              <select class="form-select" id="category-parent" name="parent_id">
                <option value="0">— Žádná —</option>
                <?php foreach ($categories as $option): ?>
                  <?php if ($category && (int)$option['id'] === (int)$category['id']) continue; ?>
                  <option value="<?= (int)$option['id'] ?>" <?= (int)($category['parent_id'] ?? 0) === (int)$option['id'] ? 'selected' : '' ?>><?= $h((string)($option['name'] ?? '')) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label" for="category-sort">Pořadí</label>
              <input class="form-control" id="category-sort" name="sort_order" type="number" value="<?= (int)($category['sort_order'] ?? 0) ?>">
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-between mt-3">
      <a class="btn btn-outline-secondary" href="admin.php?r=categories">Zpět na seznam</a>
      <button class="btn btn-primary" type="submit">Uložit</button>
    </div>
  </form>
<?php
});
