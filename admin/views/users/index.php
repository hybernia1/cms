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

$this->render('layouts/base', compact('pageTitle','nav','currentUser','flash'), function() use ($items,$pagination,$searchQuery,
$buildUrl,$csrf,$currentUser,$currentUrl) {
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
          'class' => 'btn btn-primary order-2',
        ],
      ]);
    ?>

    <?php $this->render('parts/listing/bulk-form', [
      'formId'       => 'users-bulk-form',
      'action'       => 'admin.php?r=users&a=bulk',
      'csrf'         => $csrf,
      'selectAll'    => '#users-select-all',
      'rowSelector'  => '.user-row-check',
      'actionSelect' => '#users-bulk-select',
      'applyButton'  => '#users-bulk-apply',
      'counter'      => '#users-bulk-counter',
      'hidden'       => [
        'q'    => $searchQuery,
        'page' => (string)($pagination['page'] ?? 1),
      ],
    ]); ?>

    <div class="card">
      <?php $this->render('parts/listing/bulk-header', [
        'formId'         => 'users-bulk-form',
        'actionSelectId' => 'users-bulk-select',
        'applyButtonId'  => 'users-bulk-apply',
        'options'        => [
          ['value' => 'delete', 'label' => 'Smazat'],
        ],
        'counterId'      => 'users-bulk-counter',
        'applyIcon'      => 'bi bi-trash',
      ]); ?>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:36px"><input class="form-check-input" type="checkbox" id="users-select-all" aria-label="Vybrat všechny"></th>
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

    <?php $this->render('users/partials/pagination', [
      'pagination' => $pagination,
      'buildUrl'   => $buildUrl,
    ]); ?>
  </div>
<?php
});
