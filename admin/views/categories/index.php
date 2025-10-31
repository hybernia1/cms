<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array<int,array<string,mixed>> $items */
/** @var array{page:int,per_page:int,total:int,pages:int} $pagination */
/** @var array{q:string} $filters */
/** @var callable $buildUrl */
/** @var string $csrf */

$this->render('parts/layouts/base', compact('pageTitle', 'nav', 'currentUser', 'flash'), function () use ($items, $filters, $pagination, $buildUrl, $csrf) {
    $h = fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Kategorie produktů</h1>
    <a class="btn btn-primary" href="admin.php?r=categories&a=create">Nová kategorie</a>
  </div>

  <form class="row g-2 mb-3" method="get" action="admin.php">
    <input type="hidden" name="r" value="categories">
    <div class="col-md-4">
      <label class="form-label" for="categories-filter-q">Hledat</label>
      <input class="form-control" type="search" id="categories-filter-q" name="q" value="<?= $h($filters['q']) ?>" placeholder="Název nebo slug">
    </div>
    <div class="col-md-2 align-self-end">
      <button type="submit" class="btn btn-outline-secondary w-100">Filtrovat</button>
    </div>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>ID</th>
            <th>Název</th>
            <th>Slug</th>
            <th>Nadřazená kategorie</th>
            <th class="text-end">Pořadí</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($items === []): ?>
            <tr>
              <td colspan="6" class="text-center text-muted py-4">Zatím nejsou vytvořeny žádné kategorie.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($items as $item): ?>
              <tr>
                <td><?= (int)($item['id'] ?? 0) ?></td>
                <td><?= $h((string)($item['name'] ?? '')) ?></td>
                <td><?= $h((string)($item['slug'] ?? '')) ?></td>
                <td><?= $item['parent_name'] ? $h((string)$item['parent_name']) : '<span class="text-muted">—</span>' ?></td>
                <td class="text-end"><?= (int)($item['sort_order'] ?? 0) ?></td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm" role="group">
                    <a class="btn btn-outline-secondary" href="admin.php?r=categories&a=edit&id=<?= (int)($item['id'] ?? 0) ?>">Upravit</a>
                    <form method="post" action="admin.php?r=categories&a=delete" onsubmit="return confirm('Opravdu smazat tuto kategorii?');">
                      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                      <input type="hidden" name="id" value="<?= (int)($item['id'] ?? 0) ?>">
                      <button class="btn btn-outline-danger" type="submit">Smazat</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php $this->render('parts/pagination', ['pagination' => $pagination, 'buildUrl' => $buildUrl]); ?>
<?php
});
