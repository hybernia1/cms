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
/** @var array<string,int> $statusCounts */
/** @var callable $buildUrl */

$this->render('layouts/base', compact('pageTitle','nav','currentUser','flash'), function () use ($filters,$items,$pagination,$csrf,$type,$types,$urls,$statusCounts,$buildUrl) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $typeCfg = $types[$type] ?? ['create'=>'Nový příspěvek'];

  $status = (string)($filters['status'] ?? '');
  $q = (string)($filters['q'] ?? '');
  $statusCounts = is_array($statusCounts ?? null) ? $statusCounts : [];
  $totalCount = (int)($statusCounts['__total'] ?? 0);
  if ($totalCount === 0 && $statusCounts !== []) {
    $totalCount = array_sum(array_map(static fn($v) => is_int($v) ? $v : 0, $statusCounts));
  }

  $statusTabs = [
    ''         => 'Vše',
    'publish'  => 'Publikované',
    'draft'    => 'Koncepty',
  ];

  $statusCountFor = function(string $value) use ($statusCounts, $totalCount): int {
    if ($value === '') {
      return $totalCount;
    }
    return (int)($statusCounts[$value] ?? 0);
  };
?>
  <?php
    $tabLinks = [];
    foreach ($statusTabs as $value => $label) {
      $tabLinks[] = [
        'label'  => $label,
        'href'   => $buildUrl(['status' => $value]),
        'active' => $status === $value,
        'count'  => $statusCountFor($value),
      ];
    }

    $this->part('listing/toolbar', [
      'tabs'    => $tabLinks,
      'tabsClass' => 'order-2 order-md-1',
      'search'  => [
        'action'        => 'admin.php',
        'wrapperClass'  => 'order-1 order-md-2 ms-md-auto',
        'hidden'        => ['r' => 'posts', 'type' => $type, 'status' => $status],
        'value'         => $q,
        'placeholder'   => 'Hledat…',
        'resetHref'     => $buildUrl(['q' => '']),
        'resetDisabled' => $q === '',
        'searchTooltip' => 'Hledat',
        'clearTooltip'  => 'Zrušit filtr',
      ],
      'button' => [
        'href'  => 'admin.php?' . http_build_query(['r' => 'posts', 'a' => 'create', 'type' => $type]),
        'label' => (string)($typeCfg['create'] ?? 'Nový záznam'),
        'icon'  => 'bi bi-plus-lg',
        'class' => 'btn btn-success btn-sm order-3',
      ],
    ]);
  ?>

  <!-- Tabulka bez #ID a bez status sloupce -->
  <?php $this->part('listing/bulk-form', [
    'formId'       => 'posts-bulk-form',
    'action'       => 'admin.php?' . http_build_query(['r' => 'posts', 'a' => 'bulk', 'type' => $type]),
    'csrf'         => $csrf,
    'selectAll'    => '#select-all',
    'rowSelector'  => '.row-check',
    'actionSelect' => '#bulk-action-select',
    'applyButton'  => '#bulk-apply',
    'counter'      => '#bulk-selection-counter',
  ]); ?>
  <div class="card">
    <?php $this->part('listing/bulk-header', [
      'formId'         => 'posts-bulk-form',
      'actionSelectId' => 'bulk-action-select',
      'applyButtonId'  => 'bulk-apply',
      'options'        => [
        ['value' => 'publish', 'label' => 'Publikovat'],
        ['value' => 'draft',   'label' => 'Přepnout na koncept'],
        ['value' => 'delete',  'label' => 'Smazat'],
      ],
      'counterId'      => 'bulk-selection-counter',
      'applyIcon'      => 'bi bi-arrow-repeat',
    ]); ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:36px"><input class="form-check-input" type="checkbox" id="select-all"></th>
              <th>Název</th>
              <th style="width:200px">Vytvořeno</th>
              <th style="width:140px" class="text-end">Akce</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $it): ?>
              <?php
                $isPublished = ($it['status'] ?? '') === 'publish';
                $itemType = (string)($it['type'] ?? $type);
                $slug = (string)($it['slug'] ?? '');
                $frontUrl = '';
                if ($slug !== '') {
                  $frontUrl = $itemType === 'page'
                    ? $urls->page($slug)
                    : $urls->post($slug);
                }
              ?>
              <tr>
                <td><input class="form-check-input row-check" type="checkbox" name="ids[]" value="<?= $h((string)$it['id']) ?>" aria-label="Vybrat položku" form="posts-bulk-form"></td>
                <td>
                  <?php if ($frontUrl !== ''): ?>
                    <a class="fw-semibold text-truncate d-inline-flex align-items-center gap-1 text-decoration-none" href="<?= $h($frontUrl) ?>" target="_blank" rel="noopener">
                      <?= $h((string)($it['title'] ?? '—')) ?>
                      <i class="bi bi-box-arrow-up-right text-secondary small"></i>
                    </a>
                  <?php else: ?>
                    <div class="fw-semibold text-truncate"><?= $h((string)($it['title'] ?? '—')) ?></div>
                  <?php endif; ?>
                  <div class="text-secondary small text-truncate">
                    <i class="bi bi-link-45deg me-1"></i><?= $h($slug) ?>
                  </div>
                </td>

                <td>
                  <span class="small" title="<?= $h((string)($it['created_at_raw'] ?? '')) ?>">
                    <?= $h((string)($it['created_at_display'] ?? ($it['created_at_raw'] ?? ''))) ?>
                  </span>
                </td>

                <td class="text-end">
                  <a class="btn btn-light btn-sm border me-1"
                     href="<?= $h('admin.php?'.http_build_query(['r'=>'posts','a'=>'edit','id'=>$it['id'],'type'=>$type])) ?>"
                     aria-label="Upravit" data-bs-toggle="tooltip" data-bs-title="Upravit">
                    <i class="bi bi-pencil"></i>
                  </a>

                  <form method="post" action="<?= $h('admin.php?'.http_build_query(['r'=>'posts','a'=>'toggle','type'=>$type])) ?>" class="d-inline" data-ajax>
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

                  <form method="post" action="<?= $h('admin.php?'.http_build_query(['r'=>'posts','a'=>'delete','type'=>$type])) ?>" class="d-inline" onsubmit="return confirm('Opravdu smazat?');" data-ajax>
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
                <td colspan="4" class="text-center text-secondary py-4">
                  <i class="bi bi-inbox me-1"></i>Žádné položky
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <!-- Stránkování -->
  <?php $this->part('listing/pagination', [
    'page'      => (int)($pagination['page'] ?? 1),
    'pages'     => (int)($pagination['pages'] ?? 1),
    'buildUrl'  => $buildUrl,
    'ariaLabel' => 'Stránkování',
  ]); ?>

<?php
});
