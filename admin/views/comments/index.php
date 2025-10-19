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
/** @var array<string,int>|null $statusCounts */
/** @var callable $buildUrl */

$this->render('layouts/base', compact('pageTitle','nav','currentUser','flash'), function () use ($filters,$items,$pagination,$csrf,$statusCounts,$buildUrl) {
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
    <form method="post"
          action="admin.php?r=comments&a=delete"
          class="d-inline"
          data-ajax
          data-confirm-modal="Opravdu smazat? Smaže i odpovědi."
          data-confirm-modal-title="Potvrzení smazání"
          data-confirm-modal-confirm-label="Smazat"
          data-confirm-modal-cancel-label="Zrušit">
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

  $statusCounts = is_array($statusCounts ?? null) ? $statusCounts : [];
  $totalCount = (int)($statusCounts['__total'] ?? 0);
  if ($totalCount === 0 && $statusCounts !== []) {
    $totalCount = array_sum(array_map(static fn($v) => is_int($v) ? $v : 0, $statusCounts));
  }
  $statusCountFor = function(string $value) use ($statusCounts, $totalCount): int {
    if ($value === '') {
      return $totalCount;
    }
    return (int)($statusCounts[$value] ?? 0);
  };

  $status = (string)($filters['status'] ?? '');
  $q = (string)($filters['q'] ?? '');
  $postFilter = (string)($filters['post'] ?? '');

  $tabs = [
    ''          => 'Vše',
    'draft'     => 'Koncepty',
    'published' => 'Schválené',
    'spam'      => 'Spam',
  ];

  $tabLinks = [];
  foreach ($tabs as $value => $label) {
    $tabLinks[] = [
      'label'  => $label,
      'href'   => $buildUrl(['status' => $value !== '' ? $value : null, 'page' => null]),
      'active' => $status === $value,
      'count'  => $statusCountFor($value),
    ];
  }

  $this->render('parts/listing/toolbar', [
    'tabs'   => $tabLinks,
    'search' => [
      'action'        => 'admin.php',
      'wrapperClass'  => 'order-1 order-md-2 ms-md-auto',
      'hidden'        => ['r' => 'comments', 'status' => $status, 'post' => $postFilter],
      'value'         => $q,
      'placeholder'   => 'Text komentáře, autor, e-mail…',
      'resetHref'     => $buildUrl(['q' => null, 'page' => null]),
      'resetDisabled' => $q === '',
      'searchTooltip' => 'Hledat',
      'clearTooltip'  => 'Zrušit filtr',
    ],
  ]);
?>
  <div class="card mb-3">
    <div class="card-body">
      <form class="row gy-2 gx-2 align-items-end" method="get" action="admin.php" data-ajax>
        <input type="hidden" name="r" value="comments">
        <input type="hidden" name="status" value="<?= $h($status) ?>">
        <input type="hidden" name="q" value="<?= $h($q) ?>">
        <div class="col-md-6 col-lg-4">
          <label class="form-label" for="filter-post">Příspěvek</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text">Post</span>
            <input class="form-control" id="filter-post" name="post" placeholder="slug nebo ID" value="<?= $h($postFilter) ?>">
            <button class="btn btn-primary" type="submit">Filtrovat</button>
            <a class="btn btn-outline-secondary <?= $postFilter === '' ? 'disabled' : '' ?>"
               href="<?= $h($buildUrl(['post' => null, 'page' => null])) ?>"
               aria-label="Zrušit filtr"
               data-bs-toggle="tooltip"
               data-bs-title="Zrušit filtr">
              <i class="bi bi-x-circle"></i>
            </a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <?php $this->render('parts/listing/bulk-form', [
    'formId'       => 'comments-bulk-form',
    'action'       => 'admin.php?r=comments&a=bulk',
    'csrf'         => $csrf,
    'selectAll'    => '#comments-select-all',
    'rowSelector'  => '.comment-row-check',
    'actionSelect' => '#comments-bulk-select',
    'applyButton'  => '#comments-bulk-apply',
    'counter'      => '#comments-bulk-counter',
    'hidden'       => [
      'status' => $status,
      'q'      => $q,
      'post'   => $postFilter,
      'page'   => (string)($pagination['page'] ?? 1),
    ],
  ]); ?>

  <div class="card">
    <?php $this->render('parts/listing/bulk-header', [
      'formId'         => 'comments-bulk-form',
      'actionSelectId' => 'comments-bulk-select',
      'applyButtonId'  => 'comments-bulk-apply',
      'options'        => [
        ['value' => 'published', 'label' => 'Schválit'],
        ['value' => 'draft',     'label' => 'Uložit jako koncept'],
        ['value' => 'spam',      'label' => 'Označit jako spam'],
        ['value' => 'delete',    'label' => 'Smazat'],
      ],
      'counterId'      => 'comments-bulk-counter',
      'applyIcon'      => 'bi bi-arrow-repeat',
    ]); ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:36px"><input class="form-check-input" type="checkbox" id="comments-select-all" aria-label="Vybrat všechny"></th>
            <th>Autor / E-mail</th>
            <th>Text</th>
            <th>Příspěvek</th>
            <th style="width:220px" class="text-end">Akce</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $c):
            $statusValue = (string)($c['status'] ?? '');
            $statusLabel = $statusLabels[$statusValue] ?? $statusValue;
            $createdDisplay = (string)($c['created_at_display'] ?? ($c['created_at_raw'] ?? ''));
            $createdIso = (string)($c['created_at_iso'] ?? '');
          ?>
            <tr>
              <td>
                <input class="form-check-input comment-row-check" type="checkbox" name="ids[]" value="<?= $h((string)$c['id']) ?>" aria-label="Vybrat komentář" form="comments-bulk-form">
              </td>
              <td>
                <div class="fw-semibold text-truncate"><?= $h((string)($c['author_name'] ?? '')) ?></div>
                <div class="small text-secondary text-truncate"><i class="bi bi-envelope me-1"></i><?= $h((string)($c['author_email'] ?? '')) ?></div>
              </td>
              <td>
                <div class="text-truncate" style="max-width:420px;">
                  <?= $h(mb_substr((string)$c['content'], 0, 160)) ?><?= mb_strlen((string)$c['content']) > 160 ? '…' : '' ?>
                </div>
                <div class="small text-secondary d-flex align-items-center gap-2 flex-wrap mt-1">
                  <?php if ($createdDisplay !== ''): ?>
                    <?php if ($createdIso !== ''): ?>
                      <time datetime="<?= $h($createdIso) ?>"><?= $h($createdDisplay) ?></time>
                    <?php else: ?>
                      <span><?= $h($createdDisplay) ?></span>
                    <?php endif; ?>
                  <?php endif; ?>
                  <span class="badge rounded-pill text-bg-<?= $badge($statusValue) ?>"><?= $h($statusLabel) ?></span>
                </div>
              </td>
              <td>
                <a class="small" href="admin.php?r=posts&a=edit&id=<?= (int)$c['post_id'] ?>">#<?= (int)$c['post_id'] ?></a>
                <div class="small text-secondary text-truncate" style="max-width:180px;">
                  <?= $h((string)$c['post_title']) ?>
                </div>
              </td>
              <td class="text-end">
                <div class="d-flex justify-content-end flex-wrap gap-1">
                  <a class="btn btn-light btn-sm border px-2" href="admin.php?r=comments&a=show&id=<?= (int)$c['id'] ?>" aria-label="Detail" data-bs-toggle="tooltip" data-bs-title="Detail">
                    <i class="bi bi-eye"></i>
                  </a>
                  <?php foreach ($statusActionOrder as $actionKey): ?>
                    <?php if (in_array($actionKey, $statusActions[$statusValue] ?? [], true)): ?>
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

  <?php $this->render('parts/listing/pagination', [
    'page'      => (int)($pagination['page'] ?? 1),
    'pages'     => (int)($pagination['pages'] ?? 1),
    'buildUrl'  => $buildUrl,
    'ariaLabel' => 'Stránkování',
  ]); ?>
<?php
});
