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
/** @var \Cms\Admin\View\Listing\BulkConfig $bulkConfig */

$this->render('parts/layouts/base', compact('pageTitle','nav','currentUser','flash'), function () use ($filters,$items,$pagination,$csrf,$type,$types,$urls,$buildUrl,$bulkConfig) {
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

    <?php $this->render('parts/listing/bulk-form', $bulkConfig->formParams()); ?>

    <div class="card" data-terms-card>
      <?php $this->render('parts/listing/bulk-header', $bulkConfig->headerParams([
        ['value' => 'delete', 'label' => 'Smazat'],
      ], 'bi bi-trash3')); ?>

      <?php $this->render('terms/partials/table', [
        'items' => $items,
        'type'  => $type,
        'urls'  => $urls,
        'csrf'  => $csrf,
        'bulkConfig' => $bulkConfig,
      ]); ?>
    </div>

    <?php $this->render('parts/listing/pagination-block', [
      'pagination'        => $pagination,
      'buildUrl'          => $buildUrl,
      'wrapperAttributes' => ['data-terms-pagination' => ''],
    ]); ?>
  </div>

<?php
});
