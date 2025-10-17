<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array $data */
/** @var string $q */
/** @var string $csrf */

$this->render('layouts/base', compact('pageTitle','nav','currentUser','flash'), function() use ($data,$q,$csrf) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $items = $data['items'] ?? [];
  $buildUrl = function(array $override = []) use ($q): string {
    $qs = $_GET ?? [];
    unset($qs['page']);
    $qs = array_merge(['r' => 'users'], $qs, ['q' => $q], $override);
    return 'admin.php?' . http_build_query($qs);
  };
?>
  <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-2 mb-3">
    <form class="order-1 order-md-1" method="get" action="admin.php" role="search" data-ajax>
      <input type="hidden" name="r" value="users">
      <div class="input-group input-group-sm" style="min-width:260px;">
        <input class="form-control" name="q" placeholder="Hledat jméno nebo e-mail…" value="<?= $h($q) ?>">
        <button class="btn btn-outline-secondary" type="submit" aria-label="Hledat" data-bs-toggle="tooltip" data-bs-title="Hledat">
          <i class="bi bi-search"></i>
        </button>
        <a class="btn btn-outline-secondary <?= $q === '' ? 'disabled' : '' ?>" href="<?= $h($buildUrl(['q'=>''])) ?>" aria-label="Zrušit filtr" data-bs-toggle="tooltip" data-bs-title="Zrušit filtr">
          <i class="bi bi-x-circle"></i>
        </a>
      </div>
    </form>

    <a class="btn btn-success btn-sm order-2" href="admin.php?r=users&a=edit">
      <i class="bi bi-plus-lg me-1"></i>Nový uživatel
    </a>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Jméno</th>
            <th style="width:200px">Role</th>
            <th style="width:120px" class="text-center">Stav</th>
            <th style="width:200px">Vytvořeno</th>
            <th style="width:140px" class="text-end">Akce</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $u): ?>
            <tr>
              <td>
                <div class="fw-semibold text-truncate"><?= $h((string)($u['name'] ?? '—')) ?></div>
                <div class="text-secondary small text-truncate"><i class="bi bi-envelope me-1"></i><?= $h((string)($u['email'] ?? '')) ?></div>
              </td>
              <td>
                <?php $role = (string)($u['role'] ?? 'user'); ?>
                <?php if ($role === 'admin'): ?>
                  <span class="badge text-bg-warning-subtle text-warning-emphasis border border-warning-subtle">Administrátor</span>
                <?php else: ?>
                  <span class="badge text-bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle">Uživatel</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?php $active = (int)($u['active'] ?? 0) === 1; ?>
                <span class="badge <?= $active ? 'text-bg-success-subtle text-success-emphasis border border-success-subtle' : 'text-bg-danger-subtle text-danger-emphasis border border-danger-subtle' ?>">
                  <?= $active ? 'Aktivní' : 'Neaktivní' ?>
                </span>
              </td>
              <td>
                <span class="small" title="<?= $h((string)($u['created_at_raw'] ?? '')) ?>">
                  <?= $h((string)($u['created_at_display'] ?? ($u['created_at_raw'] ?? ''))) ?>
                </span>
              </td>
              <td class="text-end">
                <a class="btn btn-light btn-sm border" href="admin.php?r=users&a=edit&id=<?= (int)($u['id'] ?? 0) ?>" aria-label="Upravit" data-bs-toggle="tooltip" data-bs-title="Upravit">
                  <i class="bi bi-pencil"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$items): ?>
            <tr>
              <td colspan="5" class="text-center text-secondary py-4">
                <i class="bi bi-inbox me-1"></i>Nic nenalezeno
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if (($data['pages'] ?? 1) > 1): ?>
    <nav class="mt-3" aria-label="Stránkování">
      <ul class="pagination pagination-sm mb-0">
        <?php
          $page  = (int)($data['page'] ?? 1);
          $pages = (int)($data['pages'] ?? 1);
          $base  = $buildUrl();
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
<?php }); ?>
