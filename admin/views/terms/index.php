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

$this->render('parts/layouts/base', compact('pageTitle','nav','currentUser','flash'), function () use ($filters,$items,$pagination,$csrf,$type,$types,$urls,$buildUrl) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $currentUrl = $buildUrl(['page' => (int)($pagination['page'] ?? 1)]);
?>
  <div
    data-terms-listing
    data-terms-type="<?= $h($type) ?>"
    data-terms-url="<?= $h($currentUrl) ?>"
  >
    <?php $this->render('terms/partials/toolbar', [
      'filters'  => $filters,
      'type'     => $type,
      'types'    => $types,
      'buildUrl' => $buildUrl,
    ]); ?>

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

    <div class="card" data-terms-card>
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

      <?php $this->render('terms/partials/table', [
        'items' => $items,
        'type'  => $type,
        'urls'  => $urls,
        'csrf'  => $csrf,
      ]); ?>
    </div>

    <?php $this->render('terms/partials/pagination', [
      'pagination' => $pagination,
      'buildUrl'   => $buildUrl,
    ]); ?>
  </div>

<?php
});
