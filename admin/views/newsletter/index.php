<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array<int,array<string,mixed>> $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array<int,array<string,mixed>> $items */
/** @var array{page:int,per_page:int,total:int,pages:int} $pagination */
/** @var array{status:string,q:string} $filters */
/** @var array<string,array{label:string,badge:string}> $statusMeta */
/** @var callable $buildUrl */
/** @var string $csrf */
/** @var string $currentUrl */

$this->render('parts/layouts/base', compact('pageTitle', 'nav', 'currentUser', 'flash'), function () use ($items, $pagination, $filters, $statusMeta, $buildUrl, $csrf, $currentUrl) {
    $h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $statusOptions = ['' => 'Všechny stavy'];
    foreach ($statusMeta as $key => $meta) {
        $statusOptions[$key] = $meta['label'];
    }
    $total = (int)($pagination['total'] ?? 0);
?>
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-end mb-3">
    <form class="row g-2 align-items-end" method="get" action="admin.php">
      <input type="hidden" name="r" value="newsletter">
      <div class="col-auto">
        <label class="form-label" for="newsletter-filter-status">Stav</label>
        <select class="form-select form-select-sm" id="newsletter-filter-status" name="status">
          <?php foreach ($statusOptions as $value => $label): ?>
            <option value="<?= $h($value) ?>"<?= $filters['status'] === $value ? ' selected' : '' ?>><?= $h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label" for="newsletter-filter-q">Hledat</label>
        <input
          class="form-control form-control-sm"
          type="search"
          id="newsletter-filter-q"
          name="q"
          placeholder="E-mail nebo zdroj"
          value="<?= $h($filters['q']) ?>"
        >
      </div>
      <div class="col-auto d-flex gap-2">
        <button class="btn btn-primary btn-sm" type="submit">Filtrovat</button>
        <a class="btn btn-outline-secondary btn-sm" href="<?= $h((string)$buildUrl(['status' => null, 'q' => null, 'page' => null])) ?>">Vymazat</a>
      </div>
    </form>
    <div class="d-flex gap-2 ms-auto">
      <span class="text-secondary align-self-center small">Celkem: <?= $total ?></span>
      <a class="btn btn-outline-secondary btn-sm" href="admin.php?r=newsletter&a=export">
        <i class="bi bi-filetype-csv"></i>
        Export
      </a>
    </div>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>E-mail</th>
            <th style="width:160px">Stav</th>
            <th style="width:200px">Zdroj</th>
            <th style="width:170px">Vytvořeno</th>
            <th style="width:170px">Potvrzeno</th>
            <th style="width:170px">Odhlášeno</th>
            <th style="width:220px" class="text-end">Akce</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($items === []): ?>
            <tr>
              <td class="text-center text-secondary" colspan="7">Žádní odběratelé.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($items as $item): ?>
              <tr>
                <td>
                  <a href="<?= $h($item['detail_url'] ?? 'admin.php?r=newsletter') ?>" class="link-underline link-underline-opacity-0">
                    <?= $h((string)($item['email'] ?? '')) ?>
                  </a>
                </td>
                <td>
                  <span class="badge text-bg-<?= $h((string)($item['status_badge'] ?? 'secondary')) ?>">
                    <?= $h((string)($item['status_label'] ?? $item['status'] ?? '')) ?>
                  </span>
                </td>
                <td>
                  <?php $source = (string)($item['source_url'] ?? ''); ?>
                  <?php if ($source !== ''): ?>
                    <a href="<?= $h($source) ?>" target="_blank" rel="noreferrer noopener" class="text-truncate d-inline-block" style="max-width:180px;">
                      <?= $h($source) ?>
                    </a>
                  <?php else: ?>
                    <span class="text-secondary">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (!empty($item['created_at_display'])): ?>
                    <?= $h((string)$item['created_at_display']) ?>
                  <?php else: ?>
                    <span class="text-secondary">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (!empty($item['confirmed_at_display'])): ?>
                    <?= $h((string)$item['confirmed_at_display']) ?>
                  <?php else: ?>
                    <span class="text-secondary">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (!empty($item['unsubscribed_at_display'])): ?>
                    <?= $h((string)$item['unsubscribed_at_display']) ?>
                  <?php else: ?>
                    <span class="text-secondary">—</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <div class="d-flex flex-wrap justify-content-end gap-1">
                    <a class="btn btn-outline-primary btn-sm" href="<?= $h($item['detail_url'] ?? 'admin.php?r=newsletter') ?>">Detail</a>
                    <?php if (($item['status'] ?? '') !== 'confirmed'): ?>
                      <form method="post" action="admin.php?r=newsletter&a=confirm" class="d-inline">
                        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int)($item['id'] ?? 0) ?>">
                        <input type="hidden" name="redirect" value="<?= $h($currentUrl) ?>">
                        <button class="btn btn-outline-success btn-sm" type="submit">Potvrdit</button>
                      </form>
                    <?php endif; ?>
                    <?php if (($item['status'] ?? '') !== 'unsubscribed'): ?>
                      <form method="post" action="admin.php?r=newsletter&a=unsubscribe" class="d-inline">
                        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int)($item['id'] ?? 0) ?>">
                        <input type="hidden" name="redirect" value="<?= $h($currentUrl) ?>">
                        <button class="btn btn-outline-warning btn-sm" type="submit">Odhlásit</button>
                      </form>
                    <?php endif; ?>
                    <form
                      method="post"
                      action="admin.php?r=newsletter&a=delete"
                      class="d-inline"
                      data-confirm-modal="Opravdu odstranit tuto adresu?"
                      data-confirm-modal-title="Smazat odběratele"
                      data-confirm-modal-confirm-label="Smazat"
                      data-confirm-modal-cancel-label="Zrušit"
                    >
                      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                      <input type="hidden" name="id" value="<?= (int)($item['id'] ?? 0) ?>">
                      <input type="hidden" name="redirect" value="<?= $h($currentUrl) ?>">
                      <button class="btn btn-outline-danger btn-sm" type="submit">Smazat</button>
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

  <?php $this->render('parts/listing/pagination', [
    'page'     => (int)($pagination['page'] ?? 1),
    'pages'    => (int)($pagination['pages'] ?? 1),
    'buildUrl' => $buildUrl,
  ]); ?>
<?php
});
