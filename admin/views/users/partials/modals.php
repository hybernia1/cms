<?php
declare(strict_types=1);
/** @var array<int,array<string,mixed>> $items */
/** @var array{page:int,per_page:int,total:int,pages:int} $pagination */
/** @var string $searchQuery */
/** @var string $csrf */
/** @var int $currentUserId */

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$pageNumber = (int)($pagination['page'] ?? 1);
?>
<div data-users-modals>
<?php foreach ($items as $u):
  $role = (string)($u['role'] ?? 'user');
  $userId = (int)($u['id'] ?? 0);
  $active = (int)($u['active'] ?? 0) === 1;
  $canDelete = $userId > 0 && $role !== 'admin' && $userId !== $currentUserId;
  $canToggle = $userId > 0 && $role !== 'admin' && $userId !== $currentUserId;
  $toggleModalId = 'user-toggle-' . $userId;
  $deleteModalId = 'user-delete-' . $userId;
  $toggleEmailId = $toggleModalId . '-email';
  $deleteEmailId = $deleteModalId . '-email';
  $toggleActionLabel = $active ? 'Deaktivovat' : 'Aktivovat';
?>
  <?php if ($canToggle): ?>
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
            <button type="submit" class="<?= $active ? 'btn btn-warning' : 'btn btn-success' ?>"><?= $toggleActionLabel ?></button>
            <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
            <input type="hidden" name="id" value="<?= $h((string)$userId) ?>">
            <input type="hidden" name="status" value="<?= $active ? '0' : '1' ?>">
            <input type="hidden" name="q" value="<?= $h($searchQuery) ?>">
            <input type="hidden" name="page" value="<?= $h((string)$pageNumber) ?>">
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>
  <?php if ($canDelete): ?>
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
  <?php endif; ?>
<?php endforeach; ?>
</div>
