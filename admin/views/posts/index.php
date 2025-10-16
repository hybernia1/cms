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

  // helper: postav URL s přepsanými parametry (např. status), resetuje page
  $buildUrl = function(array $override = [] ) use ($type) : string {
    $qs = $_GET ?? [];
    unset($qs['page']);
    $qs = array_merge(['r'=>'posts','type'=>$type], $qs, $override);
    return 'admin.php?'.http_build_query($qs);
  };

  $status = (string)($filters['status'] ?? '');
  $q = (string)($filters['q'] ?? '');

  $statusTabs = [
    ''         => 'Vše',
    'publish'  => 'Publikované',
    'draft'    => 'Koncepty',
  ];
?>
  <!-- Horní lišta: přepínače + minimalistický search + "Nový" -->
  <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-2 mb-3">
    <!-- Status tabs -->
    <nav aria-label="Filtr statusu" class="order-2 order-md-1">
      <ul class="nav nav-pills nav-sm">
        <?php foreach ($statusTabs as $value => $label): ?>
          <li class="nav-item">
            <a class="nav-link px-3 py-1 <?= $status===$value ? 'active' : '' ?>"
               href="<?= $h($buildUrl(['status'=>$value])) ?>">
               <?= $h($label) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <!-- Minimal search (ikonka) -->
    <form class="order-1 order-md-2 ms-md-auto" method="get" action="admin.php" role="search">
      <input type="hidden" name="r" value="posts">
      <input type="hidden" name="type" value="<?= $h($type) ?>">
      <input type="hidden" name="status" value="<?= $h($status) ?>">

      <div class="input-group input-group-sm" style="min-width:260px;">
        <input class="form-control" name="q" placeholder="Hledat…" value="<?= $h($q) ?>">
        <button class="btn btn-outline-secondary" type="submit" aria-label="Hledat" data-bs-toggle="tooltip" data-bs-title="Hledat">
          <i class="bi bi-search"></i>
        </button>
        <a class="btn btn-outline-secondary <?= $q === '' ? 'disabled' : '' ?>"
           href="<?= $h($buildUrl(['q'=>''])) ?>"
           aria-label="Zrušit filtr" data-bs-toggle="tooltip" data-bs-title="Zrušit filtr">
          <i class="bi bi-x-circle"></i>
        </a>
      </div>
    </form>

    <!-- New button -->
    <a class="btn btn-success btn-sm order-3" href="<?= $h('admin.php?'.http_build_query(['r'=>'posts','a'=>'create','type'=>$type])) ?>">
      <i class="bi bi-plus-lg me-1"></i><?= $h((string)($typeCfg['create'] ?? 'Nový záznam')) ?>
    </a>
  </div>

  <!-- Tabulka bez #ID a bez status sloupce -->
  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Název</th>
            <th style="width:200px">Vytvořeno</th>
            <th style="width:140px" class="text-end">Akce</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $it): ?>
            <?php $isPublished = ($it['status'] ?? '') === 'publish'; ?>
            <tr>
              <td>
                <div class="fw-semibold text-truncate"><?= $h((string)($it['title'] ?? '—')) ?></div>
                <div class="text-secondary small text-truncate">
                  <i class="bi bi-link-45deg me-1"></i><?= $h((string)($it['slug'] ?? '')) ?>
                </div>
              </td>

              <td>
                <span class="small" title="<?= $h((string)($it['created_at'] ?? '')) ?>">
                  <?= $h((string)($it['created_at'] ?? '')) ?>
                </span>
              </td>

              <td class="text-end">
                <!-- Upravit -->
                <a class="btn btn-light btn-sm border me-1"
                   href="<?= $h('admin.php?'.http_build_query(['r'=>'posts','a'=>'edit','id'=>$it['id'],'type'=>$type])) ?>"
                   aria-label="Upravit" data-bs-toggle="tooltip" data-bs-title="Upravit">
                  <i class="bi bi-pencil"></i>
                </a>

                <!-- Toggle publikace (oko) -->
                <form method="post" action="<?= $h('admin.php?'.http_build_query(['r'=>'posts','a'=>'toggle','type'=>$type])) ?>" class="d-inline">
                  <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                  <input type="hidden" name="id" value="<?= $h((string)$it['id']) ?>">
                  <button class="btn btn-light btn-sm border me-1" type="submit"
                          aria-label="<?= $isPublished ? 'Zneviditelnit' : 'Publikovat' ?>"
                          data-bs-toggle="tooltip" data-bs-title="<?= $isPublished ? 'Zneviditelnit' : 'Publikovat' ?>">
                    <?php if ($isPublished): ?>
                      <i class="bi bi-eye"></i>
                    <?php else: ?>
                      <i class="bi bi-eye-slash"></i>
                    <?php endif; ?>
                  </button>
                </form>

                <!-- Smazat -->
                <form method="post" action="<?= $h('admin.php?'.http_build_query(['r'=>'posts','a'=>'delete','type'=>$type])) ?>" class="d-inline" onsubmit="return confirm('Opravdu smazat?');">
                  <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                  <input type="hidden" name="id" value="<?= $h((string)$it['id']) ?>">
                  <button class="btn btn-light btn-sm border"
                          type="submit" aria-label="Smazat"
                          data-bs-toggle="tooltip" data-bs-title="Smazat">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (!$items): ?>
            <tr>
              <td colspan="3" class="text-center text-secondary py-4">
                <i class="bi bi-inbox me-1"></i>Žádné položky
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Stránkování -->
  <?php if (($pagination['pages'] ?? 1) > 1): ?>
    <nav class="mt-3" aria-label="Stránkování">
      <ul class="pagination pagination-sm mb-0">
        <?php
          $page  = (int)($pagination['page']  ?? 1);
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

  <!-- Tooltip init (BS5) -->
  <script>
    (function () {
      var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      tooltipTriggerList.forEach(function (el) {
        new bootstrap.Tooltip(el);
      });
    })();
  </script>
<?php
});
