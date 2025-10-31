<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array<int,array<string,mixed>> $orders */
/** @var array{status:string,q:string} $filters */
/** @var array<int,string> $statuses */
/** @var array{page:int,per_page:int,total:int,pages:int} $pagination */
/** @var callable $buildUrl */

$this->render('parts/layouts/base', compact('pageTitle', 'nav', 'currentUser', 'flash'), function () use ($orders, $filters, $statuses, $pagination, $buildUrl) {
    $h = fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Objednávky</h1>
  </div>

  <form class="row g-2 mb-3" method="get" action="admin.php">
    <input type="hidden" name="r" value="orders">
    <div class="col-md-3">
      <label class="form-label" for="orders-filter-status">Stav</label>
      <select class="form-select" id="orders-filter-status" name="status">
        <option value="">— Všechny —</option>
        <?php foreach ($statuses as $status): ?>
          <option value="<?= $h($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= $h(ucfirst($status)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label" for="orders-filter-q">Hledat</label>
      <input class="form-control" type="search" id="orders-filter-q" name="q" value="<?= $h($filters['q']) ?>" placeholder="číslo objednávky nebo e-mail">
    </div>
    <div class="col-md-2 align-self-end">
      <button class="btn btn-outline-secondary w-100" type="submit">Filtrovat</button>
    </div>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Číslo objednávky</th>
            <th>Zákazník</th>
            <th>Stav</th>
            <th class="text-end">Celkem</th>
            <th class="text-end">Vytvořeno</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($orders === []): ?>
            <tr>
              <td colspan="6" class="text-center text-muted py-4">Zatím nejsou žádné objednávky.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($orders as $order): ?>
              <tr>
                <td><strong><?= $h((string)($order['order_number'] ?? '')) ?></strong></td>
                <td>
                  <?php $name = trim(((string)($order['first_name'] ?? '')) . ' ' . ((string)($order['last_name'] ?? ''))); ?>
                  <?= $name !== '' ? $h($name) : '<span class="text-muted">Nepřiřazeno</span>' ?>
                  <?php if (!empty($order['email'])): ?>
                    <div class="small text-muted"><?= $h((string)$order['email']) ?></div>
                  <?php endif; ?>
                </td>
                <td><span class="badge text-bg-light text-uppercase"><?= $h((string)($order['status'] ?? '')) ?></span></td>
                <td class="text-end"><?= number_format((float)($order['total'] ?? 0), 2, ',', ' ') ?> <?= $h((string)($order['currency'] ?? '')) ?></td>
                <td class="text-end text-muted small"><?= $h((string)($order['created_at_display'] ?? '')) ?></td>
                <td class="text-end">
                  <a class="btn btn-outline-secondary btn-sm" href="admin.php?r=orders&a=detail&id=<?= (int)($order['id'] ?? 0) ?>">Detail</a>
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
