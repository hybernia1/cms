<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array $filters */
/** @var array<int,array{id:int,user_id:int,type:string,mime:string,url:string,rel_path:?string,created_at:string}> $items */
/** @var array{page:int,per_page:int,total:int,pages:int} $pagination */
/** @var string $csrf */

$this->render('layouts/base', compact('pageTitle','nav','currentUser','flash'), function () use ($filters,$items,$pagination,$csrf) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
  <div class="card mb-3">
    <div class="card-body">
      <form class="row gy-2 gx-2 align-items-end" method="get" action="admin.php">
        <input type="hidden" name="r" value="media">
        <div class="col-md-3">
          <label class="form-label" for="media-type">Typ</label>
          <select class="form-select form-select-sm" name="type" id="media-type">
            <option value="">Všechny</option>
            <?php foreach (['image'=>'Obrázky','file'=>'Soubory'] as $value=>$label): ?>
              <option value="<?= $h($value) ?>" <?= ($filters['type'] ?? '')===$value?'selected':'' ?>><?= $h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="media-search">Hledat (URL/MIME)</label>
          <div class="input-group input-group-sm">
            <input class="form-control" id="media-search" name="q" value="<?= $h((string)($filters['q'] ?? '')) ?>" placeholder="např. .jpg nebo image/">
            <button class="btn btn-outline-secondary" type="submit" aria-label="Hledat" data-bs-toggle="tooltip" data-bs-title="Hledat">
              <i class="bi bi-search"></i>
            </button>
            <a class="btn btn-outline-secondary <?= ($filters['q'] ?? '') === '' && ($filters['type'] ?? '') === '' ? 'disabled' : '' ?>" href="admin.php?r=media" aria-label="Zrušit filtr" data-bs-toggle="tooltip" data-bs-title="Zrušit filtr">
              <i class="bi bi-x-circle"></i>
            </a>
          </div>
        </div>
        <div class="col-md-3 d-grid">
          <button class="btn btn-primary btn-sm" type="submit">Filtrovat</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <form method="post" action="admin.php?r=media&a=upload" enctype="multipart/form-data" class="row gy-2 gx-2 align-items-end">
        <div class="col-md-6">
          <label class="form-label" for="media-files">Nahrát soubor(y)</label>
          <input class="form-control" id="media-files" type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.webp,.gif,.svg,.pdf,.zip,.txt,.csv,image/*,application/pdf,application/zip,text/plain,text/csv">
          <div class="form-text">Uloží se do <code>uploads/Y/m/media/</code>.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">&nbsp;</label>
          <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
          <button class="btn btn-success btn-sm w-100" type="submit">
            <i class="bi bi-cloud-upload me-1"></i>Nahrát
          </button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <?php if (!$items): ?>
        <div class="text-secondary"><i class="bi bi-inbox me-1"></i>Žádná média.</div>
      <?php else: ?>
        <div class="row g-3">
          <?php foreach ($items as $m):
            $isImg = str_starts_with((string)$m['mime'], 'image/');
          ?>
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
              <div class="card h-100 shadow-sm">
                <div class="card-body">
                  <div class="mb-2 d-flex justify-content-center align-items-center bg-body-tertiary rounded" style="min-height:140px; overflow:hidden;">
                    <?php if ($isImg): ?>
                      <img src="<?= $h((string)$m['url']) ?>" alt="media" style="max-height:140px;max-width:100%;object-fit:contain;">
                    <?php else: ?>
                      <div class="text-center text-secondary small">
                        <div class="mb-2">Soubor</div>
                        <div><code><?= $h((string)$m['mime']) ?></code></div>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div class="small text-secondary mb-2 text-truncate"><i class="bi bi-tag me-1"></i><?= $h((string)$m['mime']) ?></div>
                  <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-light btn-sm border" target="_blank" href="<?= $h((string)$m['url']) ?>">Otevřít</a>
                    <button class="btn btn-light btn-sm border" type="button" onclick="navigator.clipboard.writeText('<?= $h((string)$m['url']) ?>').then(()=>this.textContent='Zkopírováno');">Kopírovat URL</button>
                  </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                  <span class="small text-secondary">#<?= $h((string)$m['id']) ?> • <?= $h(date('Y-m-d', strtotime($m['created_at']))) ?></span>
                  <form method="post" action="admin.php?r=media&a=delete" onsubmit="return confirm('Opravdu odstranit?');">
                    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                    <input type="hidden" name="id" value="<?= $h((string)$m['id']) ?>">
                    <button class="btn btn-light btn-sm border" type="submit">Smazat</button>
                  </form>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if (($pagination['pages'] ?? 1) > 1): ?>
          <nav class="mt-3" aria-label="Stránkování">
            <ul class="pagination pagination-sm mb-0">
              <?php
                $page = (int)($pagination['page'] ?? 1);
                $pages = (int)($pagination['pages'] ?? 1);
                $qs = $_GET; unset($qs['page']);
                $base = 'admin.php?'.http_build_query(array_merge(['r'=>'media'], $qs));
              ?>
              <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="<?= $h($base.'&page='.max(1,$page-1)) ?>" aria-label="Předchozí">‹</a></li>
              <?php for($i=max(1,$page-2); $i<=min($pages,$page+2); $i++): ?>
                <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="<?= $h($base.'&page='.$i) ?>"><?= $i ?></a></li>
              <?php endfor; ?>
              <li class="page-item <?= $page>=$pages?'disabled':'' ?>"><a class="page-link" href="<?= $h($base.'&page='.min($pages,$page+1)) ?>" aria-label="Další">›</a></li>
            </ul>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
<?php
});
