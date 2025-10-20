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
/** @var array<string,mixed> $toolbar */
/** @var array<string,mixed> $bulkForm */
/** @var array<string,mixed> $context */

$this->render('layouts/base', compact('pageTitle','nav','currentUser','flash'), function () use ($filters,$items,$pagination,$csrf,$type,$types,$urls,$statusCounts,$buildUrl,$toolbar,$bulkForm,$context) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $status = (string)($filters['status'] ?? '');
  $q = (string)($filters['q'] ?? '');
  $statusCounts = is_array($statusCounts ?? null) ? $statusCounts : [];
  $totalCount = (int)($statusCounts['__total'] ?? 0);
  if ($totalCount === 0 && $statusCounts !== []) {
    $totalCount = array_sum(array_map(static fn($v) => is_int($v) ? $v : 0, $statusCounts));
  }

?>
  <?php $this->render('posts/parts/toolbar', ['toolbar' => $toolbar]); ?>

  <!-- Tabulka bez #ID a bez status sloupce -->
  <?php $this->render('posts/parts/bulk-form', ['bulkForm' => $bulkForm]); ?>
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
          <tbody data-admin-fragment="posts-table-body">
            <?php $this->render('posts/parts/table-rows', [
              'items' => $items,
              'type' => $type,
              'csrf' => $csrf,
              'urls' => $urls,
              'context' => $context,
            ]); ?>
          </tbody>
        </table>
      </div>
    </div>

  <!-- Stránkování -->
  <?php $this->render('posts/parts/pagination', [
    'pagination' => $pagination + ['ariaLabel' => 'Stránkování příspěvků'],
    'buildUrl'   => $buildUrl,
  ]); ?>

<?php
});
