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

$this->render('layouts/base', compact('pageTitle','nav','currentUser','flash'), function() use ($items,$pagination,$searchQuery,$buildUrl,$csrf,$currentUser) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $currentUserId = (int)($currentUser['id'] ?? 0);
?>
  <?php
    $this->part('listing/toolbar', [
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

  <?php $this->part('listing/bulk-form', [
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
    <?php $this->part('listing/bulk-header', [
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
        <tbody>
          <?php foreach ($items as $u):
            $role = (string)($u['role'] ?? 'user');
            $userId = (int)($u['id'] ?? 0);
            $canDelete = $userId > 0 && $role !== 'admin' && $userId !== $currentUserId;
            $reason = $role === 'admin' ? 'Administrátory nelze mazat.' : 'Nelze smazat vlastní účet.';
            $active = (int)($u['active'] ?? 0) === 1;
          ?>
            <tr>
              <td>
                <?php if ($canDelete): ?>
                  <input class="form-check-input user-row-check" type="checkbox" name="ids[]" value="<?= $h((string)$userId) ?>" aria-label="Vybrat uživatele" form="users-bulk-form">
                <?php else: ?>
                  <span class="text-secondary" data-bs-toggle="tooltip" data-bs-title="<?= $h($reason) ?>">
                    <i class="bi bi-shield-lock"></i>
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <div class="fw-semibold text-truncate"><?= $h((string)($u['name'] ?? '—')) ?></div>
                <div class="text-secondary small text-truncate"><i class="bi bi-envelope me-1"></i><?= $h((string)($u['email'] ?? '')) ?></div>
              </td>
              <td>
                <?php if ($role === 'admin'): ?>
                  <span class="badge text-bg-warning-subtle text-warning-emphasis border border-warning-subtle">Administrátor</span>
                <?php else: ?>
                  <span class="badge text-bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle">Uživatel</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <span class="badge <?= $active ? 'text-bg-success-subtle text-success-emphasis border border-success-subtle' : 'text-bg-danger-subtle text-danger-emphasis border border-danger-subtle' ?>">
                  <?= $active ? 'Aktivní' : 'Neaktivní' ?>
                </span>
              </td>
              <td>
                <span class="small" title="<?= $h((string)($u['created_at_raw'] ?? '')) ?>">
                  <?= $h((string)($u['created_at_display'] ?? ($u['created_at_raw'] ?? ''))) ?>
                </span>
              </td>
              <td class="text-end">
                <a class="btn btn-light btn-sm border" href="admin.php?r=users&a=edit&id=<?= $userId ?>" aria-label="Upravit" data-bs-toggle="tooltip" data-bs-title="Upravit">
                  <i class="bi bi-pencil"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$items): ?>
            <tr>
              <td colspan="6" class="text-center text-secondary py-4">
                <i class="bi bi-inbox me-1"></i>Nic nenalezeno
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php $this->part('listing/pagination', [
    'page'      => (int)($pagination['page'] ?? 1),
    'pages'     => (int)($pagination['pages'] ?? 1),
    'buildUrl'  => $buildUrl,
    'ariaLabel' => 'Stránkování',
  ]); ?>
<?php }); ?>
