<?php
declare(strict_types=1);
/** @var array<int,array<string,mixed>> $items */
/** @var array{page:int,per_page:int,total:int,pages:int} $pagination */
/** @var string $searchQuery */
/** @var string $csrf */
/** @var int $currentUserId */

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<tbody data-users-table-body>
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
  $toggleActionLabel = $active ? 'Deaktivovat' : 'Aktivovat';
  $toggleTooltip = $active ? 'Skrýt uživatele' : 'Zviditelnit uživatele';
  $toggleIcon = $active ? 'bi bi-eye-slash' : 'bi bi-eye';
  $toggleButtonClass = $active ? 'btn btn-warning' : 'btn btn-success';
?>
  <tr data-user-row data-user-id="<?= $userId ?>">
    <td>
      <?php if ($canDelete): ?>
        <input
          class="form-check-input user-row-check"
          type="checkbox"
          name="ids[]"
          value="<?= $h((string)$userId) ?>"
          aria-label="Vybrat uživatele"
          form="users-bulk-form"
        >
      <?php else: ?>
        <span class="text-secondary" data-bs-toggle="tooltip" data-bs-title="<?= $h($deleteReason) ?>">
          <i class="bi bi-shield-lock"></i>
        </span>
      <?php endif; ?>
    </td>
    <td>
      <div class="admin-table-stack">
        <div class="admin-table-line fw-semibold" title="<?= $h((string)($u['name'] ?? '—')) ?>">
          <?= $h((string)($u['name'] ?? '—')) ?>
        </div>
        <?php if (!empty($u['email'])): ?>
          <div class="admin-table-line admin-table-line--muted" title="<?= $h((string)($u['email'] ?? '')) ?>">
            <i class="bi bi-envelope" aria-hidden="true"></i>
            <span><?= $h((string)($u['email'] ?? '')) ?></span>
          </div>
        <?php endif; ?>
      </div>
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
      <span class="admin-table-line admin-table-line--muted" title="<?= $h((string)($u['created_at_raw'] ?? '')) ?>">
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
<?php endforeach; ?>
<?php if (!$items): ?>
  <tr>
    <td colspan="6" class="text-center text-secondary py-4">
      <i class="bi bi-inbox me-1"></i>Nic nenalezeno
    </td>
  </tr>
<?php endif; ?>
</tbody>
