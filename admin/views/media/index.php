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

$this->render('layouts/base', compact('pageTitle','nav','currentUser'), function () use ($flash,$filters,$items,$pagination,$csrf) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
  <?php if ($flash): ?>
    <div class="alert alert-<?= $h((string)$flash['type']) ?>"><?= $h((string)$flash['msg']) ?></div>
  <?php endif; ?>

  <div class="card mb-3">
    <div class="card-body">
      <form class="row g-2 align-items-end" method="get" action="admin.php">
        <input type="hidden" name="r" value="media">
        <div class="col-md-3">
          <label class="form-label">Typ</label>
          <select class="form-select" name="type">
            <option value="">— všechny —</option>
            <?php foreach (['image','file'] as $t): ?>
              <option value="<?= $t ?>" <?= $filters['type']===$t?'selected':'' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Hledat (URL/MIME)</label>
          <input class="form-control" name="q" value="<?= $h((string)$filters['q']) ?>" placeholder="např. .jpg nebo image/">
        </div>
        <div class="col-md-3 d-grid">
          <button class="btn btn-primary" type="submit">Filtrovat</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <form method="post" action="admin.php?r=media&a=upload" enctype="multipart/form-data">
        <label class="form-label">Nahrát soubor(y)</label>
        <input class="form-control mb-2" type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.webp,.gif,.svg,.pdf,.zip,.txt,.csv,image/*,application/pdf,application/zip,text/plain,text/csv">
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <button class="btn btn-success" type="submit">Nahrát</button>
        <span class="text-secondary small ms-2">Uloží se do <code>uploads/Y/m/media/</code>.</span>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <?php if (!$items): ?>
        <div class="text-secondary">Žádná média.</div>
      <?php else: ?>
        <div class="row g-3">
          <?php foreach ($items as $m):
            $isImg = str_starts_with((string)$m['mime'], 'image/');
          ?>
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
              <div class="card h-100">
                <div class="card-body">
                  <div class="mb-2" style="display:grid;place-items:center;min-height:140px;background:#0f0f10;border-radius:.5rem;overflow:hidden">
                    <?php if ($isImg): ?>
                      <img src="<?= $h((string)$m['url']) ?>" alt="media" style="max-height:140px;max-width:100%;object-fit:cover">
                    <?php else: ?>
                      <div class="text-center text-secondary small">
                        <div class="mb-2">Soubor</div>
                        <div><code><?= $h((string)$m['mime']) ?></code></div>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div class="small text-secondary mb-2"><?= $h((string)$m['mime']) ?></div>
                  <div class="d-flex gap-2">
                    <a class="btn btn-sm btn-outline-primary" target="_blank" href="<?= $h((string)$m['url']) ?>">Otevřít</a>
                    <button class="btn btn-sm btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText('<?= $h((string)$m['url']) ?>').then(()=>this.textContent='Zkopírováno');">Kopírovat URL</button>
                  </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                  <span class="small text-secondary">#<?= $h((string)$m['id']) ?> • <?= $h(date('Y-m-d', strtotime($m['created_at']))) ?></span>
                  <form method="post" action="admin.php?r=media&a=delete" onsubmit="return confirm('Opravdu odstranit?');">
                    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                    <input type="hidden" name="id" value="<?= $h((string)$m['id']) ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit">Smazat</button>
                  </form>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if (($pagination['pages'] ?? 1) > 1): ?>
          <nav class="mt-3">
            <ul class="pagination">
              <?php
                $page = (int)$pagination['page']; $pages = (int)$pagination['pages'];
                $qs = $_GET; unset($qs['page']); $base = 'admin.php?'.http_build_query(array_merge(['r'=>'media'], $qs));
              ?>
              <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="<?= $base.'&page='.max(1,$page-1) ?>">‹</a></li>
              <?php for($i=1;$i<=$pages;$i++): ?>
                <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="<?= $base.'&page='.$i ?>"><?= $i ?></a></li>
              <?php endfor; ?>
              <li class="page-item <?= $page>=$pages?'disabled':'' ?>"><a class="page-link" href="<?= $base.'&page='.min($pages,$page+1) ?>">›</a></li>
            </ul>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
<?php
});
