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
  $typeCfg = $types[$type] ?? ['create' => 'Nový term'];
  $buildUrl = function(array $override = []) use ($type) : string {
    $qs = $_GET ?? [];
    unset($qs['page']);
    $qs = array_merge(['r'=>'terms','type'=>$type], $qs, $override);
    return 'admin.php?'.http_build_query($qs);
  };
?>
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3 mb-3">
    <nav aria-label="Typ termu">
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

    <form class="ms-lg-auto" method="get" action="admin.php" role="search">
      <input type="hidden" name="r" value="terms">
      <input type="hidden" name="type" value="<?= $h($type) ?>">
      <div class="input-group input-group-sm">
        <input class="form-control" name="q" placeholder="Hledat název/slug…" value="<?= $h((string)$filters['q']) ?>">
        <button class="btn btn-outline-secondary" type="submit" aria-label="Hledat">
          <i class="bi bi-search"></i>
        </button>
        <a class="btn btn-outline-secondary <?= ($filters['q'] ?? '') === '' ? 'disabled' : '' ?>" href="<?= $h($buildUrl(['q'=>''])) ?>" aria-label="Zrušit filtr">
          <i class="bi bi-x-circle"></i>
        </a>
      </div>
    </form>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="h5 m-0"><?= $h((string)($typeCfg['list'] ?? 'Termy')) ?></h2>
    <a class="btn btn-success" href="<?= $h('admin.php?'.http_build_query(['r'=>'terms','a'=>'create','type'=>$type])) ?>">
      <?= $h((string)($typeCfg['create'] ?? 'Nový term')) ?>
    </a>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-dark table-hover align-middle mb-0">
        <thead>
          <tr>
            <th style="width:80px">ID</th>
            <th>Název</th>
            <th style="width:180px">Slug</th>
            <th style="width:180px">Vytvořeno</th>
            <th style="width:220px"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $it): ?>
            <tr>
              <td>#<?= $h((string)$it['id']) ?></td>
              <td class="fw-semibold"><?= $h((string)$it['name']) ?></td>
              <td><code><?= $h((string)$it['slug']) ?></code></td>
              <td><?= $h((string)($it['created_at_display'] ?? ($it['created_at_raw'] ?? ''))) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="<?= $h('admin.php?'.http_build_query(['r'=>'terms','a'=>'edit','id'=>$it['id'],'type'=>$type])) ?>">Upravit</a>
                <form method="post" action="<?= $h('admin.php?'.http_build_query(['r'=>'terms','a'=>'delete','type'=>$type])) ?>" style="display:inline" onsubmit="return confirm('Opravdu smazat? Bude odpojen od všech příspěvků.');">
                  <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                  <input type="hidden" name="id" value="<?= $h((string)$it['id']) ?>">
                  <button class="btn btn-sm btn-outline-danger" type="submit">Smazat</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$items): ?>
            <tr><td colspan="6" class="text-center text-secondary py-4">Žádné termy</td></tr>
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
          $qs = $_GET; unset($qs['page']); $base = 'admin.php?'.http_build_query(array_merge(['r'=>'terms','type'=>$type], $qs));
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
