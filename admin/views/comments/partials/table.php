<?php
declare(strict_types=1);
/** @var array<int,array<string,mixed>> $items */
/** @var string $csrf */
/** @var string|null $backUrl */

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$badge = static function (string $status): string {
    return match ($status) {
        'published' => 'success',
        'spam'      => 'danger',
        default     => 'secondary',
    };
};
$statusLabels = [
    'published' => 'Schváleno',
    'draft'     => 'Koncept',
    'spam'      => 'Spam',
];
$statusActionOrder = ['approve', 'draft', 'spam'];
$statusActions = [
    'draft'     => ['approve', 'spam'],
    'published' => ['draft', 'spam'],
    'spam'      => ['approve', 'draft'],
];
$actionDefinitions = [
    'approve' => ['route' => 'approve', 'icon' => 'bi-check-lg', 'title' => 'Schválit komentář'],
    'draft'   => ['route' => 'draft',   'icon' => 'bi-file-earmark', 'title' => 'Uložit jako koncept'],
    'spam'    => ['route' => 'spam',    'icon' => 'bi-slash-circle', 'title' => 'Označit jako spam'],
];
$currentBack = $backUrl !== null && $backUrl !== '' ? $backUrl : ((string)($_SERVER['REQUEST_URI'] ?? 'admin.php?r=comments'));

$renderStatusAction = function (string $key, array $comment) use ($actionDefinitions, $h, $csrf, $currentBack): string {
    if (!isset($actionDefinitions[$key])) {
        return '';
    }
    $def = $actionDefinitions[$key];
    ob_start();
    ?>
    <form method="post"
          action="admin.php?r=comments&a=<?= $h($def['route']) ?>"
          class="d-inline"
          data-ajax
          data-comments-action="status">
      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
      <input type="hidden" name="id" value="<?= (int)($comment['id'] ?? 0) ?>">
      <input type="hidden" name="_back" value="<?= $h($currentBack) ?>">
      <button class="btn btn-light btn-sm border px-2" type="submit" aria-label="<?= $h($def['title']) ?>" data-bs-toggle="tooltip" data-bs-title="<?= $h($def['title']) ?>">
        <i class="<?= $h($def['icon']) ?>"></i>
      </button>
    </form>
    <?php
    return trim((string)ob_get_clean());
};

$renderDeleteAction = function (array $comment) use ($h, $csrf, $currentBack): string {
    ob_start();
    ?>
    <form method="post"
          action="admin.php?r=comments&a=delete"
          class="d-inline"
          data-ajax
          data-comments-action="delete"
          data-confirm-modal="Opravdu smazat? Smaže i odpovědi."
          data-confirm-modal-title="Potvrzení smazání"
          data-confirm-modal-confirm-label="Smazat"
          data-confirm-modal-cancel-label="Zrušit">
      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
      <input type="hidden" name="id" value="<?= (int)($comment['id'] ?? 0) ?>">
      <input type="hidden" name="_back" value="<?= $h($currentBack) ?>">
      <button class="btn btn-light btn-sm border px-2 text-danger" type="submit" aria-label="Smazat" data-bs-toggle="tooltip" data-bs-title="Smazat">
        <i class="bi bi-trash"></i>
      </button>
    </form>
    <?php
    return trim((string)ob_get_clean());
};
?>
<div class="card" data-comments-table>
  <?php $this->render('parts/listing/bulk-header', [
    'formId'         => 'comments-bulk-form',
    'actionSelectId' => 'comments-bulk-select',
    'applyButtonId'  => 'comments-bulk-apply',
    'options'        => [
      ['value' => 'published', 'label' => 'Schválit'],
      ['value' => 'draft',     'label' => 'Uložit jako koncept'],
      ['value' => 'spam',      'label' => 'Označit jako spam'],
      ['value' => 'delete',    'label' => 'Smazat'],
    ],
    'counterId'      => 'comments-bulk-counter',
    'applyIcon'      => 'bi bi-arrow-repeat',
  ]); ?>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:36px"><input class="form-check-input" type="checkbox" id="comments-select-all" aria-label="Vybrat všechny"></th>
          <th>Autor / E-mail</th>
          <th>Text</th>
          <th>Příspěvek</th>
          <th style="width:220px" class="text-end">Akce</th>
        </tr>
      </thead>
      <tbody data-comments-tbody>
        <?php foreach ($items as $c):
          $statusValue = (string)($c['status'] ?? '');
          $statusLabel = $statusLabels[$statusValue] ?? $statusValue;
          $createdDisplay = (string)($c['created_at_display'] ?? ($c['created_at_raw'] ?? ''));
          $createdIso = (string)($c['created_at_iso'] ?? '');
        ?>
          <tr data-comment-row data-comment-id="<?= $h((string)($c['id'] ?? '')) ?>" data-comment-status="<?= $h($statusValue) ?>">
            <td>
              <input class="form-check-input comment-row-check" type="checkbox" name="ids[]" value="<?= $h((string)($c['id'] ?? '')) ?>" aria-label="Vybrat komentář" form="comments-bulk-form">
            </td>
            <td>
              <div class="fw-semibold text-truncate"><?= $h((string)($c['author_name'] ?? '')) ?></div>
              <div class="small text-secondary text-truncate"><i class="bi bi-envelope me-1"></i><?= $h((string)($c['author_email'] ?? '')) ?></div>
            </td>
            <td>
              <div class="text-truncate" style="max-width:420px;">
                <?= $h(mb_substr((string)($c['content'] ?? ''), 0, 160)) ?><?= mb_strlen((string)($c['content'] ?? '')) > 160 ? '…' : '' ?>
              </div>
              <div class="small text-secondary d-flex align-items-center gap-2 flex-wrap mt-1">
                <?php if ($createdDisplay !== ''): ?>
                  <?php if ($createdIso !== ''): ?>
                    <time datetime="<?= $h($createdIso) ?>"><?= $h($createdDisplay) ?></time>
                  <?php else: ?>
                    <span><?= $h($createdDisplay) ?></span>
                  <?php endif; ?>
                <?php endif; ?>
                <span class="badge rounded-pill text-bg-<?= $badge($statusValue) ?>" data-comment-status-label><?= $h($statusLabel) ?></span>
              </div>
            </td>
            <td>
              <a class="small" href="admin.php?r=posts&a=edit&id=<?= (int)($c['post_id'] ?? 0) ?>">#<?= (int)($c['post_id'] ?? 0) ?></a>
              <div class="small text-secondary text-truncate" style="max-width:180px;">
                <?= $h((string)($c['post_title'] ?? '')) ?>
              </div>
            </td>
            <td class="text-end">
              <div class="d-flex justify-content-end flex-wrap gap-1">
                <a class="btn btn-light btn-sm border px-2" href="admin.php?r=comments&a=show&id=<?= (int)($c['id'] ?? 0) ?>" aria-label="Detail" data-bs-toggle="tooltip" data-bs-title="Detail">
                  <i class="bi bi-eye"></i>
                </a>
                <?php foreach ($statusActionOrder as $actionKey): ?>
                  <?php if (in_array($actionKey, $statusActions[$statusValue] ?? [], true)): ?>
                    <?= $renderStatusAction($actionKey, $c) ?>
                  <?php endif; ?>
                <?php endforeach; ?>
                <?= $renderDeleteAction($c) ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?>
          <tr data-comments-empty-row>
            <td colspan="5" class="text-center text-secondary py-4"><i class="bi bi-inbox me-1"></i>Žádné komentáře</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
