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

$this->render('layouts/base', compact('pageTitle','nav','currentUser','flash'), function () use ($filters,$items,$pagination,$csrf,$type,$types) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $typeCfg = $types[$type] ?? ['create'=>'Nový příspěvek'];
?>
  <div class="card mb-3">
    <div class="card-body">
      <form class="row g-2" method="get" action="admin.php">
        <input type="hidden" name="r" value="posts">
        <input type="hidden" name="type" value="<?= $h($type) ?>">
        <div class="col-md-3">
          <input class="form-control" name="q" placeholder="Hledat název…" value="<?= $h((string)$filters['q']) ?>">
        </div>
        <div class="col-md-3">
          <select class="form-select" name="status">
            <option value="">— status —</option>
            <?php foreach (['draft','publish'] as $s): ?>
              <option value="<?= $s ?>" <?= $filters['status']===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3 d-grid">
          <button class="btn btn-primary" type="submit">Filtrovat</button>
        </div>
      </form>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="h5 m-0">Seznam – <?= $h((string)($typeCfg['list'] ?? 'Příspěvky')) ?></h2>
    <a class="btn btn-success" href="<?= $h('admin.php?'.http_build_query(['r'=>'posts','a'=>'create','type'=>$type])) ?>"><?= $h((string)$typeCfg['create']) ?></a>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-dark table-hover align-middle mb-0">
        <thead>
          <tr>
            <th style="width:80px">ID</th>
            <th>Název</th>
            <th style="width:120px">Typ</th>
            <th style="width:120px">Status</th>
            <th style="width:180px">Vytvořeno</th>
            <th style="width:200px"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $it): ?>
            <tr>
              <td>#<?= $h((string)$it['id']) ?></td>
              <td>
                <div class="fw-semibold"><?= $h((string)$it['title']) ?></div>
                <div class="text-secondary small"><?= $h((string)$it['slug']) ?></div>
              </td>
              <td><span class="badge text-bg-info"><?= $h((string)$it['type']) ?></span></td>
              <td>
                <span class="badge text-bg-<?= $it['status']==='publish'?'success':'secondary' ?>">
                  <?= $h((string)$it['status']) ?>
                </span>
              </td>
              <td><?= $h((string)$it['created_at']) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="<?= $h('admin.php?'.http_build_query(['r'=>'posts','a'=>'edit','id'=>$it['id'],'type'=>$type])) ?>">Upravit</a>

                <form method="post" action="<?= $h('admin.php?'.http_build_query(['r'=>'posts','a'=>'toggle','type'=>$type])) ?>" style="display:inline">
                  <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                  <input type="hidden" name="id" value="<?= $h((string)$it['id']) ?>">
                  <button class="btn btn-sm btn-outline-warning" type="submit">
                    <?= $it['status']==='publish' ? 'Zneviditelnit' : 'Publikovat' ?>
                  </button>
                </form>

                <form method="post" action="<?= $h('admin.php?'.http_build_query(['r'=>'posts','a'=>'delete','type'=>$type])) ?>" style="display:inline" onsubmit="return confirm('Opravdu smazat?');">
                  <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                  <input type="hidden" name="id" value="<?= $h((string)$it['id']) ?>">
                  <button class="btn btn-sm btn-outline-danger" type="submit">Smazat</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$items): ?>
            <tr><td colspan="6" class="text-center text-secondary py-4">Žádné položky</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if (($pagination['pages'] ?? 1) > 1): ?>
    <nav class="mt-3">
      <ul class="pagination">
        <?php
          $page = (int)$pagination['page']; $pages = (int)$pagination['pages'];
          $qs = $_GET; unset($qs['page']); $base = 'admin.php?'.http_build_query(array_merge(['r'=>'posts','type'=>$type], $qs));
        ?>
        <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="<?= $base.'&page='.max(1,$page-1) ?>">‹</a></li>
        <?php for($i=1;$i<=$pages;$i++): ?>
          <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="<?= $base.'&page='.$i ?>"><?= $i ?></a></li>
        <?php endfor; ?>
        <li class="page-item <?= $page>=$pages?'disabled':'' ?>"><a class="page-link" href="<?= $base.'&page='.min($pages,$page+1) ?>">›</a></li>
      </ul>
    </nav>
  <?php endif; ?>
<?php
});
