<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array<int,array> $items */
/** @var array{page:int,per_page:int,total:int,pages:int} $pagination */
/** @var string $searchQuery */
/** @var callable $buildUrl */
/** @var string $csrf */
/** @var string $currentUrl */
/** @var \Cms\Admin\View\Listing\BulkConfig $bulkConfig */

$this->render('parts/layouts/base', compact('pageTitle','nav','currentUser','flash'), function() use ($items,$pagination,$searchQuery,
$buildUrl,$csrf,$currentUser,$currentUrl,$bulkConfig) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $currentUserId = (int)($currentUser['id'] ?? 0);
?>
  <div
    data-users-listing
    data-users-url="<?= $h($currentUrl) ?>"
    data-users-query="<?= $h($searchQuery) ?>"
    data-users-page="<?= (int)($pagination['page'] ?? 1) ?>"
  >
    <?php
      $this->render('parts/listing/toolbar', [
        'search' => [
          'action'        => 'admin.php',
          'wrapperClass'  => 'order-1 order-md-1',
          'hidden'        => ['r' => 'users'],
          'value'         => $searchQuery,
          'placeholder'   => 'Hledat jméno nebo e-mail…',
          'resetHref'     => $buildUrl(['q' => null, 'page' => null]),
          'resetDisabled' => $searchQuery === '',
          'searchTooltip' => 'Hledat',
          'clearTooltip'  => 'Zrušit filtr',
        ],
        'button' => [
          'href'  => 'admin.php?r=users&a=edit',
          'label' => 'Nový uživatel',
          'icon'  => 'bi bi-plus-lg',
          'class' => 'btn btn-success btn-sm order-2',
        ],
      ]);
    ?>

    <?php $this->render('parts/listing/bulk-form', $bulkConfig->formParams()); ?>

    <div class="card">
      <?php $this->render('parts/listing/bulk-header', $bulkConfig->headerParams([
        ['value' => 'delete', 'label' => 'Smazat'],
      ], 'bi bi-trash')); ?>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:36px"><input class="form-check-input" type="checkbox" id="<?= $h($bulkConfig->selectAllId()) ?>" aria-label="Vybrat všechny"></th>
              <th>Jméno</th>
              <th style="width:200px">Role</th>
              <th style="width:120px" class="text-center">Stav</th>
              <th style="width:200px">Vytvořeno</th>
              <th style="width:140px" class="text-end">Akce</th>
            </tr>
          </thead>
          <?php $this->render('users/partials/table-body', [
            'items'         => $items,
            'pagination'    => $pagination,
            'searchQuery'   => $searchQuery,
            'csrf'          => $csrf,
            'currentUserId' => $currentUserId,
            'bulkConfig'    => $bulkConfig,
          ]); ?>
        </table>
      </div>
    </div>

    <?php $this->render('users/partials/modals', [
      'items'         => $items,
      'pagination'    => $pagination,
      'searchQuery'   => $searchQuery,
      'csrf'          => $csrf,
      'currentUserId' => $currentUserId,
    ]); ?>

    <?php $this->render('parts/listing/pagination-block', [
      'pagination'        => $pagination,
      'buildUrl'          => $buildUrl,
      'wrapperAttributes' => ['data-users-pagination' => ''],
    ]); ?>
  </div>
<?php
});
