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

$this->render('layouts/base', compact('pageTitle','nav','currentUser','flash'), function () use ($filters,$items,$pagination,$csrf) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
  <div class="card mb-3">
    <div class="card-body">
      <form class="row g-2" method="get" action="admin.php">
        <input type="hidden" name="r" value="terms">
        <div class="col-md-3">
          <select class="form-select" name="type">
            <option value="">— všechny typy —</option>
            <?php foreach (['category','tag'] as $t): ?>
              <option value="<?= $t ?>" <?= $filters['type']===$t?'selected':'' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <input class="form-control" name="q" placeholder="Hledat název/slug…" value="<?= $h((string)$filters['q']) ?>">
        </div>
        <div class="col-md-3 d-grid">
          <button class="btn btn-primary" type="submit">Filtrovat</button>
        </div>
      </form>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="h5 m-0">Termy</h2>
    <a class="btn btn-success" href="admin.php?r=terms&a=create">Nový term</a>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-dark table-hover align-middle mb-0">
        <thead>
          <tr>
            <th style="width:80px">ID</th>
            <th>Název</th>
            <th style="width:180px">Slug</th>
            <th style="width:140px">Typ</th>
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
              <td><span class="badge text-bg-info"><?= $h((string)$it['type']) ?></span></td>
              <td><?= $h((string)$it['created_at']) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="admin.php?r=terms&a=edit&id=<?= $h((string)$it['id']) ?>">Upravit</a>
                <form method="post" action="admin.php?r=terms&a=delete" style="display:inline" onsubmit="return confirm('Opravdu smazat? Bude odpojen od všech příspěvků.');">
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
          $qs = $_GET; unset($qs['page']); $base = 'admin.php?'.http_build_query(array_merge(['r'=>'terms'], $qs));
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
