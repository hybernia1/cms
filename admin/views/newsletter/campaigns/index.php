<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array<int,array<string,mixed>> $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array{q:string,author:int} $filters */
/** @var array<int,array<string,mixed>> $items */
/** @var array{page:int,per_page:int,total:int,pages:int} $pagination */
/** @var array<int,array{id:int,name:string,email:?string}> $authors */
/** @var callable $buildUrl */
/** @var string $csrf */
/** @var string $currentUrl */

$this->render('parts/layouts/base', compact('pageTitle', 'nav', 'currentUser', 'flash'), function () use ($filters, $items, $pagination, $authors, $buildUrl, $csrf, $currentUrl) {
    $h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $page = (int)($pagination['page'] ?? 1);
    $total = (int)($pagination['total'] ?? 0);
?>
  <div
    data-newsletter-campaigns-listing
    data-newsletter-campaigns-url="<?= $h($currentUrl) ?>"
    data-newsletter-campaigns-csrf="<?= $h($csrf) ?>"
    data-newsletter-campaigns-filter-q="<?= $h($filters['q'] ?? '') ?>"
    data-newsletter-campaigns-filter-author="<?= (int)($filters['author'] ?? 0) ?>"
    data-newsletter-campaigns-page="<?= $page ?>"
  >
    <div data-newsletter-campaigns-toolbar>
      <?php $this->render('newsletter/campaigns/partials/toolbar', [
        'filters'  => $filters,
        'authors'  => $authors,
        'buildUrl' => $buildUrl,
        'total'    => $total,
      ]); ?>
    </div>

    <div data-newsletter-campaigns-table>
      <?php $this->render('newsletter/campaigns/partials/table', [
        'items'    => $items,
        'csrf'     => $csrf,
        'filters'  => $filters,
        'page'     => $page,
      ]); ?>
    </div>

    <div data-newsletter-campaigns-pagination>
      <?php $this->render('newsletter/campaigns/partials/pagination', [
        'pagination' => $pagination,
        'buildUrl'   => $buildUrl,
      ]); ?>
    </div>
  </div>

  <?php $this->render('newsletter/campaigns/edit-modal', [
    'csrf'    => $csrf,
    'filters' => $filters,
    'page'    => $page,
  ]); ?>
  <?php $this->render('newsletter/campaigns/schedule-modal', [
    'csrf'    => $csrf,
    'filters' => $filters,
    'page'    => $page,
  ]); ?>
<?php
});
