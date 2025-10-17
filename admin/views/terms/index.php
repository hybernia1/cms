<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array $filters */
/** @var array<int,array> $items */
/** @var array{page:int,per_page:int,total:int,pages:int} $pagination */
/** @var string $csrf */
/** @var string $type */
/** @var array $types */
/** @var \Cms\Utils\LinkGenerator $urls */

$this->render('layouts/base', compact('pageTitle','nav','currentUser','flash'), function () use ($filters,$items,$pagination,$csrf,$type,$types,$urls) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $typeCfg = $types[$type] ?? ['create' => 'Nový term'];
  $buildUrl = function(array $override = []) use ($type) : string {
    $qs = $_GET ?? [];
    unset($qs['page']);
    $qs = array_merge(['r'=>'terms','type'=>$type], $qs, $override);
    return 'admin.php?'.http_build_query($qs);
  };
?>
  <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-2 mb-3">
    <nav aria-label="Typ termu" class="order-2 order-md-1">
      <ul class="nav nav-pills nav-sm">
        <?php foreach ($types as $key => $cfg): ?>
          <li class="nav-item">
            <a class="nav-link px-3 py-1 <?= $type === $key ? 'active' : '' ?>" href="<?= $h('admin.php?'.http_build_query(['r'=>'terms','type'=>$key])) ?>">
              <?= $h((string)($cfg['nav'] ?? $key)) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <form class="order-1 order-md-2 ms-md-auto" method="get" action="admin.php" role="search">
      <input type="hidden" name="r" value="terms">
      <input type="hidden" name="type" value="<?= $h($type) ?>">
      <div class="input-group input-group-sm" style="min-width:260px;">
        <input class="form-control" name="q" placeholder="Hledat…" value="<?= $h((string)($filters['q'] ?? '')) ?>">
        <button class="btn btn-outline-secondary" type="submit" aria-label="Hledat" data-bs-toggle="tooltip" data-bs-title="Hledat">
          <i class="bi bi-search"></i>
        </button>
        <a class="btn btn-outline-secondary <?= ($filters['q'] ?? '') === '' ? 'disabled' : '' ?>" href="<?= $h($buildUrl(['q'=>''])) ?>" aria-label="Zrušit filtr" data-bs-toggle="tooltip" data-bs-title="Zrušit filtr">
          <i class="bi bi-x-circle"></i>
        </a>
      </div>
    </form>

    <a class="btn btn-success btn-sm order-3" href="<?= $h('admin.php?'.http_build_query(['r'=>'terms','a'=>'create','type'=>$type])) ?>">
      <i class="bi bi-plus-lg me-1"></i><?= $h((string)($typeCfg['create'] ?? 'Nový term')) ?>
    </a>
  </div>

  <form id="terms-bulk-form"
        data-bulk-form
        data-select-all="#terms-select-all"
        data-row-checkbox=".term-row-check"
        data-action-select="#terms-bulk-action"
        data-apply-button="#terms-bulk-apply"
        data-counter="#terms-bulk-counter"
        method="post"
        data-ajax
        action="<?= $h('admin.php?'.http_build_query(['r'=>'terms','a'=>'bulk','type'=>$type])) ?>">
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
  </form>
  <div class="card">
    <div class="card-header d-flex flex-column flex-md-row gap-2 align-items-start align-items-md-center">
      <div class="d-flex flex-wrap align-items-center gap-2">
        <select class="form-select form-select-sm" name="bulk_action" id="terms-bulk-action" form="terms-bulk-form">
          <option value="">Hromadná akce…</option>
          <option value="delete">Smazat</option>
        </select>
        <button class="btn btn-primary btn-sm" type="submit" id="terms-bulk-apply" form="terms-bulk-form" disabled>
          <i class="bi bi-trash3 me-1"></i>Provést
        </button>
      </div>
      <div class="ms-md-auto small text-secondary" id="terms-bulk-counter" aria-live="polite"></div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:36px"><input class="form-check-input" type="checkbox" id="terms-select-all" aria-label="Vybrat vše"></th>
              <th>Název</th>
              <th style="width:200px">Vytvořeno</th>
              <th style="width:160px" class="text-end">Akce</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $it): ?>
              <?php
                $itemType = (string)($it['type'] ?? $type);
                $slug = (string)($it['slug'] ?? '');
                $frontUrl = $slug !== '' ? $urls->term($slug, $itemType) : '';
              ?>
              <tr>
                <td><input class="form-check-input term-row-check" type="checkbox" name="ids[]" value="<?= $h((string)$it['id']) ?>" aria-label="Vybrat term" form="terms-bulk-form"></td>
                <td>
                  <?php if ($frontUrl !== ''): ?>
                    <a class="fw-semibold text-truncate d-inline-flex align-items-center gap-1 text-decoration-none" href="<?= $h($frontUrl) ?>" target="_blank" rel="noopener">
                      <?= $h((string)($it['name'] ?? '—')) ?>
                      <i class="bi bi-box-arrow-up-right text-secondary small"></i>
                    </a>
                  <?php else: ?>
                    <div class="fw-semibold text-truncate"><?= $h((string)($it['name'] ?? '—')) ?></div>
                  <?php endif; ?>
                  <?php if (($it['description'] ?? '') !== ''): ?>
                    <div class="text-secondary small text-truncate"><?= $h((string)$it['description']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="small" title="<?= $h((string)($it['created_at_raw'] ?? '')) ?>">
                    <?= $h((string)($it['created_at_display'] ?? ($it['created_at_raw'] ?? ''))) ?>
                  </span>
                </td>
                <td class="text-end">
                  <a class="btn btn-light btn-sm border me-1" href="<?= $h('admin.php?'.http_build_query(['r'=>'terms','a'=>'edit','id'=>$it['id'],'type'=>$type])) ?>" aria-label="Upravit" data-bs-toggle="tooltip" data-bs-title="Upravit">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <form method="post" action="<?= $h('admin.php?'.http_build_query(['r'=>'terms','a'=>'delete','type'=>$type])) ?>" class="d-inline" onsubmit="return confirm('Opravdu smazat? Bude odpojen od všech příspěvků.');" data-ajax>
                    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                    <input type="hidden" name="id" value="<?= $h((string)$it['id']) ?>">
                    <button class="btn btn-light btn-sm border" type="submit" aria-label="Smazat" data-bs-toggle="tooltip" data-bs-title="Smazat">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?>
              <tr>
                <td colspan="4" class="text-center text-secondary py-4">
                  <i class="bi bi-inbox me-1"></i>Žádné termy
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php if (($pagination['pages'] ?? 1) > 1): ?>
    <nav class="mt-3" aria-label="Stránkování">
      <ul class="pagination pagination-sm mb-0">
        <?php
          $page  = (int)($pagination['page'] ?? 1);
          $pages = (int)($pagination['pages'] ?? 1);
          $base = $buildUrl();
        ?>
        <li class="page-item <?= $page<=1?'disabled':'' ?>">
          <a class="page-link" href="<?= $h($base.'&page='.max(1,$page-1)) ?>" aria-label="Předchozí">‹</a>
        </li>
        <?php for($i=max(1,$page-2); $i<=min($pages,$page+2); $i++): ?>
          <li class="page-item <?= $i===$page?'active':'' ?>">
            <a class="page-link" href="<?= $h($base.'&page='.$i) ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?= $page>=$pages?'disabled':'' ?>">
          <a class="page-link" href="<?= $h($base.'&page='.min($pages,$page+1)) ?>" aria-label="Další">›</a>
        </li>
      </ul>
    </nav>
  <?php endif; ?>

<?php
});
