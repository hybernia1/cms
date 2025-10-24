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
/** @var string $currentUrl */
/** @var \Cms\Admin\View\Listing\BulkConfig $bulkConfig */

$this->render('parts/layouts/base', compact('pageTitle','nav','currentUser','flash'), function () use ($filters,$items,$pagination,$csrf,$statusCounts,$buildUrl,$currentUrl,$bulkConfig) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
  <div
    data-comments-listing
    data-comments-url="<?= $h($currentUrl) ?>"
    data-comments-status="<?= $h((string)($filters['status'] ?? '')) ?>"
    data-comments-query="<?= $h((string)($filters['q'] ?? '')) ?>"
    data-comments-post="<?= $h((string)($filters['post'] ?? '')) ?>"
    data-comments-page="<?= (int)($pagination['page'] ?? 1) ?>"
  >
    <?php $this->render('comments/partials/toolbar', [
      'filters'      => $filters,
      'statusCounts' => $statusCounts,
      'buildUrl'     => $buildUrl,
    ]); ?>

    <?php $this->render('comments/partials/filters', [
      'filters'  => $filters,
      'buildUrl' => $buildUrl,
    ]); ?>

    <?php $this->render('comments/partials/bulk', [
      'filters'     => $filters,
      'pagination'  => $pagination,
      'csrf'        => $csrf,
      'bulkConfig'  => $bulkConfig,
    ]); ?>

    <?php $this->render('comments/partials/table', [
      'items'       => $items,
      'csrf'        => $csrf,
      'backUrl'     => $currentUrl,
      'bulkConfig'  => $bulkConfig,
    ]); ?>

    <?php $this->render('parts/listing/pagination-block', [
      'pagination'       => $pagination,
      'buildUrl'         => $buildUrl,
      'wrapperAttributes'=> ['data-comments-pagination' => ''],
    ]); ?>
  </div>
<?php
});
