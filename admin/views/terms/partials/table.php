<?php
declare(strict_types=1);
/** @var array<int,array<string,mixed>> $items */
/** @var string $type */
/** @var \Cms\Admin\Utils\LinkGenerator $urls */
/** @var string $csrf */
/** @var \Cms\Admin\View\Listing\BulkConfig $bulkConfig */

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div class="table-responsive" data-terms-table>
  <table class="table table-sm table-hover align-middle mb-0">
    <thead class="table-light">
      <tr>
        <th style="width:36px"><input class="form-check-input" type="checkbox" id="<?= $h($bulkConfig->selectAllId()) ?>" aria-label="Vybrat vše"></th>
        <th>Název</th>
        <th style="width:160px" class="text-end">Akce</th>
      </tr>
    </thead>
    <tbody data-terms-tbody>
      <?php foreach ($items as $it): ?>
        <?php
          $itemType = (string)($it['type'] ?? $type);
          $slug = (string)($it['slug'] ?? '');
          $frontUrl = $slug !== '' ? $urls->term($slug, $itemType) : '';
          $id = (int)($it['id'] ?? 0);
          $description = (string)($it['description'] ?? '');
        ?>
        <tr data-terms-row data-term-id="<?= $h((string)$id) ?>" data-term-type="<?= $h($itemType) ?>">
          <td>
            <input class="form-check-input term-row-check" type="checkbox" name="ids[]" value="<?= $h((string)$id) ?>" aria-label="Vybrat term" form="<?= $h($bulkConfig->formId()) ?>">
          </td>
          <td>
            <?php if ($frontUrl !== ''): ?>
              <a class="fw-semibold text-truncate d-inline-flex align-items-center gap-1 text-decoration-none" href="<?= $h($frontUrl) ?>" target="_blank" rel="noopener">
                <?= $h((string)($it['name'] ?? '—')) ?>
                <i class="bi bi-box-arrow-up-right text-secondary small"></i>
              </a>
            <?php else: ?>
              <div class="fw-semibold text-truncate"><?= $h((string)($it['name'] ?? '—')) ?></div>
            <?php endif; ?>
            <div class="text-secondary small text-truncate">
              <i class="bi bi-link-45deg me-1"></i><?= $h($slug) ?>
            </div>
            <?php if ($description !== ''): ?>
              <div class="text-secondary small text-truncate"><?= $h($description) ?></div>
            <?php endif; ?>
          </td>
          <td class="text-end">
            <a class="btn btn-light btn-sm border me-1" href="<?= $h('admin.php?' . http_build_query(['r' => 'terms', 'a' => 'edit', 'id' => $id, 'type' => $type])) ?>" aria-label="Upravit" data-bs-toggle="tooltip" data-bs-title="Upravit">
              <i class="bi bi-pencil"></i>
            </a>
            <?php $this->render('parts/forms/confirm-action', [
              'action'         => 'admin.php?' . http_build_query(['r' => 'terms', 'a' => 'delete', 'type' => $type]),
              'csrf'           => $csrf,
              'hidden'         => ['id' => (int)$id],
              'dataAttributes' => [
                'data-terms-delete-form' => '1',
              ],
              'button'         => [
                'tooltip'   => 'Smazat',
                'ariaLabel' => 'Smazat',
                'icon'      => 'bi bi-trash',
              ],
              'confirm'        => [
                'message' => 'Opravdu smazat? Bude odpojen od všech příspěvků.',
                'title'   => 'Potvrzení smazání',
                'confirm' => 'Smazat',
                'cancel'  => 'Zrušit',
              ],
            ]); ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$items): ?>
        <tr data-terms-empty-row>
          <td colspan="3" class="text-center text-secondary py-4">
            <i class="bi bi-inbox me-1"></i>Žádné termy
          </td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
  <template data-terms-empty-template>
    <tr data-terms-empty-row>
      <td colspan="3" class="text-center text-secondary py-4">
        <i class="bi bi-inbox me-1"></i>Žádné termy
      </td>
    </tr>
  </template>
</div>
