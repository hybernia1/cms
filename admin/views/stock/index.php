<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array<int,array<string,mixed>> $variants */
/** @var int $selectedVariant */
/** @var array<int,array<string,mixed>> $history */
/** @var array<int,array<string,mixed>> $recent */
/** @var string $csrf */

$this->render('parts/layouts/base', compact('pageTitle', 'nav', 'currentUser', 'flash'), function () use ($variants, $selectedVariant, $history, $recent, $csrf) {
    $h = fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header">
          <h2 class="card-title h5 mb-0">Ruční úprava zásob</h2>
        </div>
        <div class="card-body">
          <form method="post" action="admin.php?r=stock&a=adjust" class="row g-3">
            <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
            <input type="hidden" name="mode" value="manual">
            <div class="col-12">
              <label class="form-label" for="stock-variant">Varianta</label>
              <select class="form-select" id="stock-variant" name="variant_id" required>
                <option value="">— Vyberte —</option>
                <?php foreach ($variants as $variant): ?>
                  <option value="<?= (int)$variant['id'] ?>" <?= $selectedVariant === (int)$variant['id'] ? 'selected' : '' ?>><?= $h((string)($variant['product_name'] ?? '')) ?> — <?= $h((string)($variant['name'] ?? '')) ?> (<?= $h((string)($variant['sku'] ?? '')) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="stock-quantity">Množství</label>
              <input class="form-control" id="stock-quantity" name="quantity_change" type="number" value="0" required>
            </div>
            <div class="col-md-8">
              <label class="form-label" for="stock-reason">Důvod</label>
              <input class="form-control" id="stock-reason" name="reason" type="text" placeholder="např. Inventura">
            </div>
            <div class="col-12">
              <label class="form-label" for="stock-reference">Reference</label>
              <input class="form-control" id="stock-reference" name="reference" type="text" placeholder="např. číslo dokladu">
            </div>
            <div class="col-12 text-end">
              <button class="btn btn-primary" type="submit">Zapsat pohyb</button>
            </div>
          </form>
        </div>
      </div>

      <div class="card mt-3">
        <div class="card-header">
          <h2 class="card-title h5 mb-0">Doplnění z nákupního dokladu</h2>
        </div>
        <div class="card-body">
          <form method="post" action="admin.php?r=stock&a=adjust" class="row g-3">
            <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
            <input type="hidden" name="mode" value="invoice">
            <div class="col-12">
              <label class="form-label" for="invoice-variant">Varianta</label>
              <select class="form-select" id="invoice-variant" name="variant_id" required>
                <option value="">— Vyberte —</option>
                <?php foreach ($variants as $variant): ?>
                  <option value="<?= (int)$variant['id'] ?>"><?= $h((string)($variant['product_name'] ?? '')) ?> — <?= $h((string)($variant['name'] ?? '')) ?> (<?= $h((string)($variant['sku'] ?? '')) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="invoice-quantity">Množství</label>
              <input class="form-control" id="invoice-quantity" name="quantity_change" type="number" value="0" required>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="invoice-cost">Pořizovací cena / ks</label>
              <input class="form-control" id="invoice-cost" name="unit_cost" type="text" placeholder="např. 120.50">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="invoice-supplier">Dodavatel</label>
              <input class="form-control" id="invoice-supplier" name="supplier" type="text">
            </div>
            <div class="col-12">
              <label class="form-label" for="invoice-number">Číslo dokladu</label>
              <input class="form-control" id="invoice-number" name="invoice_number" type="text" required>
            </div>
            <div class="col-12 text-end">
              <button class="btn btn-success" type="submit">Zapsat doplnění</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title h5 mb-0">Historie varianty</h2>
          <form method="get" action="admin.php" class="d-flex align-items-center gap-2">
            <input type="hidden" name="r" value="stock">
            <select class="form-select form-select-sm" name="variant">
              <option value="0">— Vybrat variantu —</option>
              <?php foreach ($variants as $variant): ?>
                <option value="<?= (int)$variant['id'] ?>" <?= $selectedVariant === (int)$variant['id'] ? 'selected' : '' ?>><?= $h((string)($variant['product_name'] ?? '')) ?> — <?= $h((string)($variant['name'] ?? '')) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-outline-secondary btn-sm" type="submit">Zobrazit</button>
          </form>
        </div>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th>Datum</th>
                <th class="text-end">Změna</th>
                <th>Důvod</th>
                <th>Reference</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($selectedVariant === 0): ?>
                <tr>
                  <td colspan="4" class="text-center text-muted py-4">Vyberte variantu pro zobrazení historie.</td>
                </tr>
              <?php elseif ($history === []): ?>
                <tr>
                  <td colspan="4" class="text-center text-muted py-4">Zatím nejsou žádné záznamy.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($history as $entry): ?>
                  <tr>
                    <td><?= $h((string)($entry['created_at'] ?? '')) ?></td>
                    <td class="text-end">
                      <?php $qty = (int)($entry['quantity_change'] ?? 0); ?>
                      <span class="fw-semibold <?= $qty >= 0 ? 'text-success' : 'text-danger' ?>"><?= $qty >= 0 ? '+' : '' ?><?= $qty ?></span>
                    </td>
                    <td><?= $h((string)($entry['reason'] ?? '')) ?></td>
                    <td>
                      <?php if (!empty($entry['meta']) && is_array($entry['meta'])): ?>
                        <?php foreach ($entry['meta'] as $key => $value): ?>
                          <div class="small text-muted"><?= $h((string)$key) ?>: <?= $h((string)$value) ?></div>
                        <?php endforeach; ?>
                      <?php elseif (!empty($entry['reference'])): ?>
                        <span class="small text-muted"><?= $h((string)$entry['reference']) ?></span>
                      <?php else: ?>
                        <span class="text-muted">—</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h2 class="card-title h5 mb-0">Poslední pohyby</h2>
        </div>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th>Datum</th>
                <th>Varianta</th>
                <th class="text-end">Změna</th>
                <th>Důvod</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($recent === []): ?>
                <tr>
                  <td colspan="4" class="text-center text-muted py-4">Zatím nejsou žádné záznamy.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($recent as $entry): ?>
                  <tr>
                    <td><?= $h((string)($entry['created_at'] ?? '')) ?></td>
                    <td><?= $h((string)($entry['product_name'] ?? '')) ?> — <?= $h((string)($entry['variant_name'] ?? '')) ?></td>
                    <td class="text-end">
                      <?php $qty = (int)($entry['quantity_change'] ?? 0); ?>
                      <span class="fw-semibold <?= $qty >= 0 ? 'text-success' : 'text-danger' ?>"><?= $qty >= 0 ? '+' : '' ?><?= $qty ?></span>
                    </td>
                    <td>
                      <?php if (!empty($entry['meta']) && is_array($entry['meta'])): ?>
                        <?php foreach ($entry['meta'] as $key => $value): ?>
                          <div class="small text-muted"><?= $h((string)$key) ?>: <?= $h((string)$value) ?></div>
                        <?php endforeach; ?>
                      <?php elseif (!empty($entry['reference'])): ?>
                        <span class="small text-muted"><?= $h((string)$entry['reference']) ?></span>
                      <?php else: ?>
                        <span class="text-muted">—</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
<?php
});
