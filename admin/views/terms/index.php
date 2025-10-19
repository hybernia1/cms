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
/** @var \Cms\Admin\Utils\LinkGenerator $urls */
/** @var callable $buildUrl */

$this->render('layouts/base', compact('pageTitle','nav','currentUser','flash'), function () use ($filters,$items,$pagination,$csrf,$type,$types,$urls,$buildUrl) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $typeCfg = $types[$type] ?? ['create' => 'Nový term'];
?>
  <?php
    $queryValue = (string)($filters['q'] ?? '');
    $this->render('parts/listing/toolbar', [
      'search' => [
        'action'        => 'admin.php',
        'wrapperClass'  => 'order-1 flex-grow-1',
        'hidden'        => ['r' => 'terms', 'type' => $type],
        'value'         => $queryValue,
        'placeholder'   => 'Hledat…',
        'resetHref'     => $buildUrl(['q' => '']),
        'resetDisabled' => $queryValue === '',
        'searchTooltip' => 'Hledat',
        'clearTooltip'  => 'Zrušit filtr',
      ],
      'button' => [
        'href'  => 'admin.php?' . http_build_query(['r' => 'terms', 'a' => 'create', 'type' => $type]),
        'label' => (string)($typeCfg['create'] ?? 'Nový term'),
        'icon'  => 'bi bi-plus-lg',
        'class' => 'btn btn-success btn-sm order-2 order-md-2 ms-md-auto',
      ],
    ]);
  ?>

  <?php $this->render('parts/listing/bulk-form', [
    'formId'       => 'terms-bulk-form',
    'action'       => 'admin.php?' . http_build_query(['r' => 'terms', 'a' => 'bulk', 'type' => $type]),
    'csrf'         => $csrf,
    'selectAll'    => '#terms-select-all',
    'rowSelector'  => '.term-row-check',
    'actionSelect' => '#terms-bulk-action',
    'applyButton'  => '#terms-bulk-apply',
    'counter'      => '#terms-bulk-counter',
  ]); ?>
  <div class="card">
    <?php $this->render('parts/listing/bulk-header', [
      'formId'         => 'terms-bulk-form',
      'actionSelectId' => 'terms-bulk-action',
      'applyButtonId'  => 'terms-bulk-apply',
      'options'        => [
        ['value' => 'delete', 'label' => 'Smazat'],
      ],
      'counterId'      => 'terms-bulk-counter',
      'applyIcon'      => 'bi bi-trash3',
    ]); ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:36px"><input class="form-check-input" type="checkbox" id="terms-select-all" aria-label="Vybrat vše"></th>
              <th>Název</th>
              <th style="width:160px" class="text-end">Akce</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $it): ?>
              <?php
                $itemType = (string)($it['type'] ?? $type);
                $slug = (string)($it['slug'] ?? '');
                $frontUrl = $slug !== '' ? $urls->term($slug, $itemType) : '';
              ?>
              <tr>
                <td><input class="form-check-input term-row-check" type="checkbox" name="ids[]" value="<?= $h((string)$it['id']) ?>" aria-label="Vybrat term" form="terms-bulk-form"></td>
                <td>
                  <?php if ($frontUrl !== ''): ?>
                    <a class="fw-semibold text-truncate d-inline-flex align-items-center gap-1 text-decoration-none" href="<?= $h($frontUrl) ?>" target="_blank" rel="noopener">
                      <?= $h((string)($it['name'] ?? '—')) ?>
                      <i class="bi bi-box-arrow-up-right text-secondary small"></i>
                    </a>
                  <?php else: ?>
                    <div class="fw-semibold text-truncate"><?= $h((string)($it['name'] ?? '—')) ?></div>
                  <?php endif; ?>
                  <div class="text-secondary small text-truncate">
                    <i class="bi bi-link-45deg me-1"></i><?= $h($slug) ?>
                  </div>
                  <?php if (($it['description'] ?? '') !== ''): ?>
                    <div class="text-secondary small text-truncate"><?= $h((string)$it['description']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <a class="btn btn-light btn-sm border me-1" href="<?= $h('admin.php?'.http_build_query(['r'=>'terms','a'=>'edit','id'=>$it['id'],'type'=>$type])) ?>" aria-label="Upravit" data-bs-toggle="tooltip" data-bs-title="Upravit">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <form method="post"
                        action="<?= $h('admin.php?'.http_build_query(['r'=>'terms','a'=>'delete','type'=>$type])) ?>"
                        class="d-inline"
                        data-ajax
                        data-confirm-modal="Opravdu smazat? Bude odpojen od všech příspěvků."
                        data-confirm-modal-title="Potvrzení smazání"
                        data-confirm-modal-confirm-label="Smazat"
                        data-confirm-modal-cancel-label="Zrušit">
                    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                    <input type="hidden" name="id" value="<?= $h((string)$it['id']) ?>">
                    <button class="btn btn-light btn-sm border" type="submit" aria-label="Smazat" data-bs-toggle="tooltip" data-bs-title="Smazat">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?>
              <tr>
                <td colspan="3" class="text-center text-secondary py-4">
                  <i class="bi bi-inbox me-1"></i>Žádné termy
                </td>
              </tr>
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
