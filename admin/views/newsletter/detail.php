<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array<int,array<string,mixed>> $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array<string,mixed> $subscriber */
/** @var array<string,array{label:string,badge:string}> $statusMeta */
/** @var string $csrf */
/** @var string $backUrl */
/** @var string $detailUrl */

$this->render('parts/layouts/base', compact('pageTitle', 'nav', 'currentUser', 'flash'), function () use ($subscriber, $statusMeta, $csrf, $backUrl, $detailUrl) {
    $h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $status = (string)($subscriber['status'] ?? '');
    $meta = $statusMeta[$status] ?? ['label' => ucfirst($status), 'badge' => 'secondary'];
    $email = (string)($subscriber['email'] ?? '');
?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <a class="btn btn-outline-secondary btn-sm" href="<?= $h($backUrl) ?>">&larr; Zpět na seznam</a>
    <a class="btn btn-outline-secondary btn-sm" href="admin.php?r=newsletter&a=export">
      <i class="bi bi-filetype-csv"></i>
      Export potvrzených
    </a>
  </div>

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h2 class="h5 mb-0">Odběratel</h2>
        <div class="text-secondary small">ID: <?= (int)($subscriber['id'] ?? 0) ?></div>
      </div>
      <span class="badge text-bg-<?= $h((string)$meta['badge']) ?>"><?= $h((string)$meta['label']) ?></span>
    </div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-3">E-mail</dt>
        <dd class="col-sm-9">
          <a href="mailto:<?= $h($email) ?>"><?= $h($email) ?></a>
        </dd>

        <dt class="col-sm-3">Vytvořeno</dt>
        <dd class="col-sm-9">
          <?php if (!empty($subscriber['created_at_display'])): ?>
            <?= $h((string)$subscriber['created_at_display']) ?>
            <?php if (!empty($subscriber['created_at'])): ?>
              <span class="text-secondary ms-2">(<?= $h((string)$subscriber['created_at']) ?>)</span>
            <?php endif; ?>
          <?php else: ?>
            <span class="text-secondary">—</span>
          <?php endif; ?>
        </dd>

        <dt class="col-sm-3">Potvrzeno</dt>
        <dd class="col-sm-9">
          <?php if (!empty($subscriber['confirmed_at_display'])): ?>
            <?= $h((string)$subscriber['confirmed_at_display']) ?>
            <?php if (!empty($subscriber['confirmed_at'])): ?>
              <span class="text-secondary ms-2">(<?= $h((string)$subscriber['confirmed_at']) ?>)</span>
            <?php endif; ?>
          <?php else: ?>
            <span class="text-secondary">—</span>
          <?php endif; ?>
        </dd>

        <dt class="col-sm-3">Odhlášeno</dt>
        <dd class="col-sm-9">
          <?php if (!empty($subscriber['unsubscribed_at_display'])): ?>
            <?= $h((string)$subscriber['unsubscribed_at_display']) ?>
            <?php if (!empty($subscriber['unsubscribed_at'])): ?>
              <span class="text-secondary ms-2">(<?= $h((string)$subscriber['unsubscribed_at']) ?>)</span>
            <?php endif; ?>
          <?php else: ?>
            <span class="text-secondary">—</span>
          <?php endif; ?>
        </dd>

        <dt class="col-sm-3">Zdrojový URL</dt>
        <dd class="col-sm-9">
          <?php $sourceUrl = (string)($subscriber['source_url'] ?? ''); ?>
          <?php if ($sourceUrl !== ''): ?>
            <a href="<?= $h($sourceUrl) ?>" target="_blank" rel="noreferrer noopener"><?= $h($sourceUrl) ?></a>
          <?php else: ?>
            <span class="text-secondary">—</span>
          <?php endif; ?>
        </dd>

        <dt class="col-sm-3">Potvrzovací token</dt>
        <dd class="col-sm-9">
          <?php if (!empty($subscriber['confirm_token'])): ?>
            <code><?= $h((string)$subscriber['confirm_token']) ?></code>
          <?php else: ?>
            <span class="text-secondary">—</span>
          <?php endif; ?>
        </dd>

        <dt class="col-sm-3">Token expiruje</dt>
        <dd class="col-sm-9">
          <?php if (!empty($subscriber['confirm_expires_at_display'])): ?>
            <?= $h((string)$subscriber['confirm_expires_at_display']) ?>
            <?php if (!empty($subscriber['confirm_expires_at'])): ?>
              <span class="text-secondary ms-2">(<?= $h((string)$subscriber['confirm_expires_at']) ?>)</span>
            <?php endif; ?>
          <?php else: ?>
            <span class="text-secondary">—</span>
          <?php endif; ?>
        </dd>

        <dt class="col-sm-3">Token pro odhlášení</dt>
        <dd class="col-sm-9">
          <?php if (!empty($subscriber['unsubscribe_token'])): ?>
            <code><?= $h((string)$subscriber['unsubscribe_token']) ?></code>
          <?php else: ?>
            <span class="text-secondary">—</span>
          <?php endif; ?>
        </dd>
      </dl>
    </div>
    <div class="card-footer">
      <div class="d-flex flex-wrap gap-2">
        <?php if ($status !== 'confirmed'): ?>
          <form method="post" action="admin.php?r=newsletter&a=confirm">
            <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int)($subscriber['id'] ?? 0) ?>">
            <input type="hidden" name="redirect" value="<?= $h($detailUrl) ?>">
            <button class="btn btn-success" type="submit">Potvrdit odběr</button>
          </form>
        <?php endif; ?>
        <?php if ($status !== 'unsubscribed'): ?>
          <form method="post" action="admin.php?r=newsletter&a=unsubscribe">
            <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int)($subscriber['id'] ?? 0) ?>">
            <input type="hidden" name="redirect" value="<?= $h($detailUrl) ?>">
            <button class="btn btn-outline-warning" type="submit">Odhlásit</button>
          </form>
        <?php endif; ?>
        <form
          method="post"
          action="admin.php?r=newsletter&a=delete"
          data-confirm-modal="Opravdu odstranit tuto adresu?"
          data-confirm-modal-title="Smazat odběratele"
          data-confirm-modal-confirm-label="Smazat"
          data-confirm-modal-cancel-label="Zrušit"
        >
          <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
          <input type="hidden" name="id" value="<?= (int)($subscriber['id'] ?? 0) ?>">
          <input type="hidden" name="redirect" value="<?= $h($backUrl) ?>">
          <button class="btn btn-outline-danger" type="submit">Smazat</button>
        </form>
      </div>
    </div>
  </div>
<?php
});
