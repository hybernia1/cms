<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array<int,array<string,mixed>> $items */
/** @var array{page:int,per_page:int,total:int,pages:int} $pagination */
/** @var array{status:string,q:string} $filters */
/** @var callable $buildUrl */
/** @var string $csrf */

$this->render('parts/layouts/base', compact('pageTitle', 'nav', 'currentUser', 'flash'), function () use ($items, $filters, $pagination, $buildUrl, $csrf) {
    $h = fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Produkty</h1>
    <a class="btn btn-primary" href="admin.php?r=products&a=create">Nový produkt</a>
  </div>

  <form class="row g-2 mb-3" method="get" action="admin.php">
    <input type="hidden" name="r" value="products">
    <div class="col-md-3">
      <label class="form-label" for="products-filter-status">Stav</label>
      <select class="form-select" id="products-filter-status" name="status">
        <option value="">— Všechny —</option>
        <?php foreach (['draft' => 'Koncept', 'active' => 'Aktivní', 'archived' => 'Archivováno'] as $value => $label): ?>
          <option value="<?= $h($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= $h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label" for="products-filter-q">Hledat</label>
      <input class="form-control" type="search" id="products-filter-q" name="q" value="<?= $h($filters['q']) ?>" placeholder="Název nebo slug">
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
            <th>Stav</th>
            <th>Kategorie</th>
            <th class="text-end">Cena</th>
            <th class="text-end">Aktualizováno</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($items === []): ?>
            <tr>
              <td colspan="7" class="text-center text-muted py-4">Zatím nejsou vytvořeny žádné produkty.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($items as $item): ?>
              <tr>
                <td><?= (int)($item['id'] ?? 0) ?></td>
                <td>
                  <strong><?= $h((string)($item['name'] ?? '')) ?></strong>
                  <div class="text-muted small">Slug: <?= $h((string)($item['slug'] ?? '')) ?></div>
                </td>
                <td><span class="badge text-bg-light text-uppercase"><?= $h((string)($item['status'] ?? '')) ?></span></td>
                <td>
                  <?php $cats = $item['categories'] ?? []; ?>
                  <?php if ($cats === []): ?>
                    <span class="text-muted">—</span>
                  <?php else: ?>
                    <?= $h(implode(', ', array_map(static fn($c) => (string)$c, $cats))) ?>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <?= number_format((float)($item['price'] ?? 0), 2, ',', ' ') ?>
                  <span class="text-muted"><?= $h((string)($item['currency'] ?? '')) ?></span>
                </td>
                <td class="text-end text-muted small"><?= $h((string)($item['updated_at_display'] ?? '')) ?></td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm" role="group">
                    <a class="btn btn-outline-secondary" href="admin.php?r=products&a=edit&id=<?= (int)($item['id'] ?? 0) ?>">Upravit</a>
                    <form method="post" action="admin.php?r=products&a=delete" onsubmit="return confirm('Opravdu smazat tento produkt?');">
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
