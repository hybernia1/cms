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
  $badge = function(string $status): string {
    return match($status){
      'published' => 'success',
      'spam'      => 'danger',
      default     => 'secondary'
    };
  };
  $statusLabels = [
    'published' => 'Schváleno',
    'draft'     => 'Koncept',
    'spam'      => 'Spam',
  ];
  $statusActionOrder = ['approve','draft','spam'];
  $statusActions = [
    'draft'     => ['approve','spam'],
    'published' => ['draft','spam'],
    'spam'      => ['approve','draft'],
  ];
  $actionDefinitions = [
    'approve' => ['route' => 'approve', 'icon' => 'bi-check-lg', 'title' => 'Schválit komentář'],
    'draft'   => ['route' => 'draft',   'icon' => 'bi-file-earmark', 'title' => 'Uložit jako koncept'],
    'spam'    => ['route' => 'spam',    'icon' => 'bi-slash-circle', 'title' => 'Označit jako spam'],
  ];
  $backUrl = $_SERVER['REQUEST_URI'] ?? 'admin.php?r=comments';
  $renderStatusAction = function(string $key, array $comment) use ($actionDefinitions, $h, $csrf, $backUrl): string {
    if (!isset($actionDefinitions[$key])) {
      return '';
    }
    $def = $actionDefinitions[$key];
    ob_start();
    ?>
    <form method="post" action="admin.php?r=comments&a=<?= $h($def['route']) ?>" class="d-inline" data-ajax>
      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
      <input type="hidden" name="id" value="<?= (int)($comment['id'] ?? 0) ?>">
      <input type="hidden" name="_back" value="<?= $h($backUrl) ?>">
      <button class="btn btn-light btn-sm border px-2" type="submit" aria-label="<?= $h($def['title']) ?>" data-bs-toggle="tooltip" data-bs-title="<?= $h($def['title']) ?>">
        <i class="<?= $h($def['icon']) ?>"></i>
      </button>
    </form>
    <?php
    return trim((string)ob_get_clean());
  };
  $renderDeleteAction = function(array $comment) use ($h, $csrf, $backUrl): string {
    ob_start();
    ?>
    <form method="post" action="admin.php?r=comments&a=delete" class="d-inline" onsubmit="return confirm('Opravdu smazat? Smaže i odpovědi.');" data-ajax>
      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
      <input type="hidden" name="id" value="<?= (int)($comment['id'] ?? 0) ?>">
      <input type="hidden" name="_back" value="<?= $h($backUrl) ?>">
      <button class="btn btn-light btn-sm border px-2 text-danger" type="submit" aria-label="Smazat" data-bs-toggle="tooltip" data-bs-title="Smazat">
        <i class="bi bi-trash"></i>
      </button>
    </form>
    <?php
    return trim((string)ob_get_clean());
  };
?>
  <div class="card mb-3">
    <div class="card-body">
      <form class="row gy-2 gx-2 align-items-end" method="get" action="admin.php" data-ajax>
        <input type="hidden" name="r" value="comments">
        <div class="col-md-3">
          <label class="form-label" for="filter-status">Stav</label>
          <select class="form-select form-select-sm" name="status" id="filter-status">
            <option value="">Všechny stavy</option>
            <?php foreach (['draft'=>'Koncept','published'=>'Schválené','spam'=>'Spam'] as $val=>$lbl): ?>
              <option value="<?= $h($val) ?>" <?= ($filters['status'] ?? '')===$val?'selected':'' ?>><?= $h($lbl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-5">
          <label class="form-label" for="filter-q">Vyhledávání</label>
          <div class="input-group input-group-sm">
            <input class="form-control" id="filter-q" name="q" placeholder="Text komentáře, autor, e-mail…" value="<?= $h((string)($filters['q'] ?? '')) ?>">
            <button class="btn btn-outline-secondary" type="submit" aria-label="Hledat" data-bs-toggle="tooltip" data-bs-title="Hledat">
              <i class="bi bi-search"></i>
            </button>
            <a class="btn btn-outline-secondary <?= ($filters['q'] ?? '') === '' ? 'disabled' : '' ?>" href="admin.php?r=comments" aria-label="Zrušit filtr" data-bs-toggle="tooltip" data-bs-title="Zrušit filtr">
              <i class="bi bi-x-circle"></i>
            </a>
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="filter-post">Příspěvek</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text">Post</span>
            <input class="form-control" id="filter-post" name="post" placeholder="slug nebo ID" value="<?= $h((string)($filters['post'] ?? '')) ?>">
            <button class="btn btn-primary" type="submit">Filtrovat</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:80px">ID</th>
            <th>Autor / E-mail</th>
            <th>Text</th>
            <th>Příspěvek</th>
            <th style="width:220px" class="text-end">Akce</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $c):
            $status = (string)($c['status'] ?? '');
            $statusLabel = $statusLabels[$status] ?? $status;
            $createdDisplay = (string)($c['created_at_display'] ?? ($c['created_at_raw'] ?? ''));
            $createdIso = (string)($c['created_at_iso'] ?? '');
          ?>
            <tr>
              <td>#<?= $h((string)$c['id']) ?></td>
              <td>
                <div class="fw-semibold text-truncate"><?= $h((string)($c['author_name'] ?? '')) ?></div>
                <div class="small text-secondary text-truncate"><i class="bi bi-envelope me-1"></i><?= $h((string)($c['author_email'] ?? '')) ?></div>
              </td>
              <td>
                <div class="text-truncate" style="max-width:420px;"><?= $h(mb_substr((string)$c['content'],0,160)) ?><?= mb_strlen((string)$c['content'])>160?'…':'' ?></div>
                <div class="small text-secondary d-flex align-items-center gap-2 flex-wrap mt-1">
                  <?php if ($createdDisplay !== ''): ?>
                    <?php if ($createdIso !== ''): ?>
                      <time datetime="<?= $h($createdIso) ?>"><?= $h($createdDisplay) ?></time>
                    <?php else: ?>
                      <span><?= $h($createdDisplay) ?></span>
                    <?php endif; ?>
                  <?php endif; ?>
                  <span class="badge rounded-pill text-bg-<?= $badge($status) ?>"><?= $h($statusLabel) ?></span>
                </div>
              </td>
              <td>
                <a class="small" href="admin.php?r=posts&a=edit&id=<?= (int)$c['post_id'] ?>">#<?= (int)$c['post_id'] ?></a>
                <div class="small text-secondary text-truncate" style="max-width:180px;"><?= $h((string)$c['post_title']) ?></div>
              </td>
              <td class="text-end">
                <div class="d-flex justify-content-end flex-wrap gap-1">
                  <a class="btn btn-light btn-sm border px-2" href="admin.php?r=comments&a=show&id=<?= (int)$c['id'] ?>" aria-label="Detail" data-bs-toggle="tooltip" data-bs-title="Detail">
                    <i class="bi bi-eye"></i>
                  </a>
                  <?php foreach ($statusActionOrder as $actionKey): ?>
                    <?php if (in_array($actionKey, $statusActions[$status] ?? [], true)): ?>
                      <?= $renderStatusAction($actionKey, $c) ?>
                    <?php endif; ?>
                  <?php endforeach; ?>
                  <?= $renderDeleteAction($c) ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$items): ?>
            <tr><td colspan="5" class="text-center text-secondary py-4"><i class="bi bi-inbox me-1"></i>Žádné komentáře</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if (($pagination['pages'] ?? 1) > 1): ?>
    <nav class="mt-3" aria-label="Stránkování">
      <ul class="pagination pagination-sm mb-0">
        <?php
          $page = (int)($pagination['page'] ?? 1);
          $pages = (int)($pagination['pages'] ?? 1);
          $qs = $_GET; unset($qs['page']);
          $base = 'admin.php?'.http_build_query(array_merge(['r'=>'comments'], $qs));
        ?>
        <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="<?= $h($base.'&page='.max(1,$page-1)) ?>" aria-label="Předchozí">‹</a></li>
        <?php for($i=max(1,$page-2); $i<=min($pages,$page+2); $i++): ?>
          <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="<?= $h($base.'&page='.$i) ?>"><?= $i ?></a></li>
        <?php endfor; ?>
        <li class="page-item <?= $page>=$pages?'disabled':'' ?>"><a class="page-link" href="<?= $h($base.'&page='.min($pages,$page+1)) ?>" aria-label="Další">›</a></li>
      </ul>
    </nav>
  <?php endif; ?>
<?php
});
