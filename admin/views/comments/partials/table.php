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
$currentBack = $backUrl !== null && $backUrl !== '' ? $backUrl : ((string)($_SERVER['REQUEST_URI'] ?? 'admin.php?r=comments'));
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
          $commentContent = (string)($c['content'] ?? '');
          $commentPreview = mb_substr($commentContent, 0, 200);
          if (mb_strlen($commentContent) > 200) {
            $commentPreview .= '…';
          }
          $postTitle = (string)($c['post_title'] ?? '');
        ?>
          <tr data-comment-row data-comment-id="<?= $h((string)($c['id'] ?? '')) ?>" data-comment-status="<?= $h($statusValue) ?>">
            <td>
              <input class="form-check-input comment-row-check" type="checkbox" name="ids[]" value="<?= $h((string)($c['id'] ?? '')) ?>" aria-label="Vybrat komentář" form="comments-bulk-form">
            </td>
            <td>
              <div class="admin-table-stack">
                <div class="admin-table-line fw-semibold" title="<?= $h((string)($c['author_name'] ?? '')) ?>">
                  <?= $h((string)($c['author_name'] ?? '')) ?>
                </div>
                <?php if (!empty($c['author_email'])): ?>
                  <div class="admin-table-line admin-table-line--muted" title="<?= $h((string)($c['author_email'] ?? '')) ?>">
                    <i class="bi bi-envelope" aria-hidden="true"></i>
                    <span><?= $h((string)($c['author_email'] ?? '')) ?></span>
                  </div>
                <?php endif; ?>
              </div>
            </td>
            <td>
              <div class="admin-table-stack">
                <div class="admin-table-line admin-table-line--wrap" title="<?= $h($commentContent) ?>">
                  <?= $h($commentPreview) ?>
                </div>
                <div class="admin-table-meta">
                  <?php if ($createdDisplay !== ''): ?>
                    <?php if ($createdIso !== ''): ?>
                      <time datetime="<?= $h($createdIso) ?>"><?= $h($createdDisplay) ?></time>
                    <?php else: ?>
                      <span><?= $h($createdDisplay) ?></span>
                    <?php endif; ?>
                  <?php endif; ?>
                  <span class="badge rounded-pill text-bg-<?= $badge($statusValue) ?>" data-comment-status-label><?= $h($statusLabel) ?></span>
                </div>
              </div>
            </td>
            <td>
              <div class="admin-table-stack">
                <a class="admin-table-line admin-table-line--muted text-decoration-none" href="admin.php?r=posts&a=edit&id=<?= (int)($c['post_id'] ?? 0) ?>">
                  #<?= (int)($c['post_id'] ?? 0) ?>
                </a>
                <?php if ($postTitle !== ''): ?>
                  <div class="admin-table-line admin-table-line--muted" title="<?= $h($postTitle) ?>">
                    <?= $h($postTitle) ?>
                  </div>
                <?php endif; ?>
              </div>
            </td>
            <td class="text-end">
              <?php $this->render('parts/comments/actions', [
                'comment'            => $c,
                'csrf'               => $csrf,
                'backUrl'            => $currentBack,
                'wrapperClass'       => 'd-flex justify-content-end flex-wrap gap-1',
                'showDetail'         => true,
                'detailUrl'          => 'admin.php?r=comments&a=show&id=' . (int)($c['id'] ?? 0),
                'detailTooltip'      => 'Detail',
                'detailAriaLabel'    => 'Detail',
              ]); ?>
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
