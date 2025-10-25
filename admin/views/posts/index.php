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
/** @var array<string,int> $statusCounts */
/** @var callable $buildUrl */

$this->render('parts/layouts/base', compact('pageTitle','nav','currentUser','flash'), function () use ($filters,$items,$pagination,$csrf,$type,$types,$urls,$statusCounts,$buildUrl) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $currentUrl = (string)($_SERVER['REQUEST_URI'] ?? '');
?>
  <div
    data-posts-listing
    data-posts-type="<?= $h($type) ?>"
    data-posts-url="<?= $h($currentUrl) ?>"
  >
    <div data-posts-toolbar>
      <?php $this->render('posts/partials/toolbar', [
        'filters'      => $filters,
        'type'         => $type,
        'types'        => $types,
        'urls'         => $urls,
        'statusCounts' => $statusCounts,
        'buildUrl'     => $buildUrl,
      ]); ?>
    </div>

    <?php $this->render('parts/listing/bulk-form', [
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
      <?php $this->render('parts/listing/bulk-header', [
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
      <div data-posts-table>
        <?php $this->render('posts/partials/table', [
          'items' => $items,
          'csrf'  => $csrf,
          'type'  => $type,
          'urls'  => $urls,
        ]); ?>
      </div>
    </div>

    <div data-posts-pagination>
      <?php $this->render('posts/partials/pagination', [
        'pagination' => $pagination,
        'buildUrl'   => $buildUrl,
      ]); ?>
    </div>
  </div>

<?php
});
