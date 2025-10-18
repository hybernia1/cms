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
          <?php $rowModals = []; ?>
          <?php foreach ($items as $u):
            $role = (string)($u['role'] ?? 'user');
            $userId = (int)($u['id'] ?? 0);
            $active = (int)($u['active'] ?? 0) === 1;
            $canDelete = $userId > 0 && $role !== 'admin' && $userId !== $currentUserId;
            $deleteReason = $role === 'admin' ? 'Administrátory nelze mazat.' : 'Nelze smazat vlastní účet.';
            $canToggle = $userId > 0 && $role !== 'admin' && $userId !== $currentUserId;
            $toggleReason = $role === 'admin' ? 'Stav administrátora nelze měnit.' : 'Nelze upravit vlastní účet.';
            $toggleModalId = 'user-toggle-' . $userId;
            $deleteModalId = 'user-delete-' . $userId;
            $toggleEmailId = $toggleModalId . '-email';
            $deleteEmailId = $deleteModalId . '-email';
            $toggleActionLabel = $active ? 'Deaktivovat' : 'Aktivovat';
            $toggleTooltip = $active ? 'Skrýt uživatele' : 'Zviditelnit uživatele';
            $toggleIcon = $active ? 'bi bi-eye-slash' : 'bi bi-eye';
            $toggleButtonClass = $active ? 'btn btn-warning' : 'btn btn-success';
            $pageNumber = (int)($pagination['page'] ?? 1);
          ?>
            <tr>
              <td>
                <?php if ($canDelete): ?>
                  <input class="form-check-input user-row-check" type="checkbox" name="ids[]" value="<?= $h((string)$userId) ?>" aria-label="Vybrat uživatele" form="users-bulk-form">
                <?php else: ?>
                  <span class="text-secondary" data-bs-toggle="tooltip" data-bs-title="<?= $h($deleteReason) ?>">
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
                <a class="btn btn-light btn-sm border me-1" href="admin.php?r=users&a=edit&id=<?= $userId ?>" aria-label="Upravit" data-bs-toggle="tooltip" data-bs-title="Upravit">
                  <i class="bi bi-pencil"></i>
                </a>
                <?php if ($canToggle): ?>
                  <span class="d-inline-block me-1" data-bs-toggle="tooltip" data-bs-title="<?= $h($toggleTooltip) ?>">
                    <button class="btn btn-light btn-sm border" type="button" data-bs-toggle="modal" data-bs-target="#<?= $h($toggleModalId) ?>" aria-label="<?= $h($toggleActionLabel) ?>">
                      <i class="<?= $toggleIcon ?>"></i>
                    </button>
                  </span>
                <?php else: ?>
                  <span class="btn btn-light btn-sm border disabled me-1" data-bs-toggle="tooltip" data-bs-title="<?= $h($toggleReason) ?>">
                    <i class="<?= $toggleIcon ?>"></i>
                  </span>
                <?php endif; ?>
                <?php if ($canDelete): ?>
                  <span class="d-inline-block" data-bs-toggle="tooltip" data-bs-title="Smazat uživatele">
                    <button class="btn btn-light btn-sm border" type="button" data-bs-toggle="modal" data-bs-target="#<?= $h($deleteModalId) ?>" aria-label="Smazat">
                      <i class="bi bi-trash"></i>
                    </button>
                  </span>
                <?php else: ?>
                  <span class="btn btn-light btn-sm border disabled" data-bs-toggle="tooltip" data-bs-title="<?= $h($deleteReason) ?>">
                    <i class="bi bi-trash"></i>
                  </span>
                <?php endif; ?>
              </td>
            </tr>
            <?php if ($canToggle):
              ob_start();
            ?>
              <div class="modal fade" id="<?= $h($toggleModalId) ?>" tabindex="-1" aria-labelledby="<?= $h($toggleModalId) ?>Label" aria-hidden="true">
                <div class="modal-dialog">
                  <form class="modal-content" method="post" action="admin.php?r=users&a=toggle">
                    <div class="modal-header">
                      <h5 class="modal-title" id="<?= $h($toggleModalId) ?>Label">Změnit stav uživatele</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zavřít"></button>
                    </div>
                    <div class="modal-body">
                      <p>Opravdu chcete <?= $active ? 'deaktivovat' : 'aktivovat' ?> uživatele <strong><?= $h((string)($u['name'] ?? '')) ?></strong>?</p>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="send_email" value="1" id="<?= $h($toggleEmailId) ?>">
                        <label class="form-check-label" for="<?= $h($toggleEmailId) ?>">Odeslat uživateli informační e-mail</label>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zrušit</button>
                      <button type="submit" class="<?= $toggleButtonClass ?>"><?= $toggleActionLabel ?></button>
                      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                      <input type="hidden" name="id" value="<?= $h((string)$userId) ?>">
                      <input type="hidden" name="status" value="<?= $active ? '0' : '1' ?>">
                      <input type="hidden" name="q" value="<?= $h($searchQuery) ?>">
                      <input type="hidden" name="page" value="<?= $h((string)$pageNumber) ?>">
                    </div>
                  </form>
                </div>
              </div>
            <?php
              $modal = ob_get_clean();
              if ($modal !== false) { $rowModals[] = $modal; }
            endif; ?>
            <?php if ($canDelete):
              ob_start();
            ?>
              <div class="modal fade" id="<?= $h($deleteModalId) ?>" tabindex="-1" aria-labelledby="<?= $h($deleteModalId) ?>Label" aria-hidden="true">
                <div class="modal-dialog">
                  <form class="modal-content" method="post" action="admin.php?r=users&a=delete">
                    <div class="modal-header">
                      <h5 class="modal-title" id="<?= $h($deleteModalId) ?>Label">Smazat uživatele</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zavřít"></button>
                    </div>
                    <div class="modal-body">
                      <p>Opravdu chcete smazat uživatele <strong><?= $h((string)($u['name'] ?? '')) ?></strong>? Tato akce je nevratná.</p>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="send_email" value="1" id="<?= $h($deleteEmailId) ?>">
                        <label class="form-check-label" for="<?= $h($deleteEmailId) ?>">Odeslat uživateli informační e-mail</label>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zrušit</button>
                      <button type="submit" class="btn btn-danger">Smazat</button>
                      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                      <input type="hidden" name="id" value="<?= $h((string)$userId) ?>">
                      <input type="hidden" name="q" value="<?= $h($searchQuery) ?>">
                      <input type="hidden" name="page" value="<?= $h((string)$pageNumber) ?>">
                    </div>
                  </form>
                </div>
              </div>
            <?php
              $modal = ob_get_clean();
              if ($modal !== false) { $rowModals[] = $modal; }
            endif; ?>
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

  <?php foreach ($rowModals as $modal): ?>
    <?= $modal ?>
  <?php endforeach; ?>

  <?php $this->part('listing/pagination', [
    'page'      => (int)($pagination['page'] ?? 1),
    'pages'     => (int)($pagination['pages'] ?? 1),
    'buildUrl'  => $buildUrl,
    'ariaLabel' => 'Stránkování',
  ]); ?>
<?php }); ?>
