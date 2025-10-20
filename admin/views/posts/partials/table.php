<?php
declare(strict_types=1);

/**
 * @var array<int,array<string,mixed>> $items
 * @var string $csrf
 * @var string $type
 * @var \Cms\Admin\Utils\LinkGenerator|null $urls
 */

$items = is_array($items ?? null) ? $items : [];
$type = (string)($type ?? 'post');
$csrf = (string)($csrf ?? '');
$urls = $urls ?? new \Cms\Admin\Utils\LinkGenerator();

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div class="table-responsive">
  <table class="table table-sm table-hover align-middle mb-0">
    <thead class="table-light">
      <tr>
        <th style="width:36px"><input class="form-check-input" type="checkbox" id="select-all"></th>
        <th>Název</th>
        <th style="width:200px">Vytvořeno</th>
        <th style="width:140px" class="text-end">Akce</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $it): ?>
        <?php
          $isPublished = ($it['status'] ?? '') === 'publish';
          $itemType = (string)($it['type'] ?? $type);
          $slug = (string)($it['slug'] ?? '');
          $frontUrl = '';
          if ($slug !== '') {
            $frontUrl = $itemType === 'page'
              ? $urls->page($slug)
              : $urls->post($slug);
          }
        ?>
        <tr>
          <td><input class="form-check-input row-check" type="checkbox" name="ids[]" value="<?= $h((string)$it['id']) ?>" aria-label="Vybrat položku" form="posts-bulk-form"></td>
          <td>
            <?php if ($frontUrl !== ''): ?>
              <a class="fw-semibold text-truncate d-inline-flex align-items-center gap-1 text-decoration-none" href="<?= $h($frontUrl) ?>" target="_blank" rel="noopener">
                <?= $h((string)($it['title'] ?? '—')) ?>
                <i class="bi bi-box-arrow-up-right text-secondary small"></i>
              </a>
            <?php else: ?>
              <div class="fw-semibold text-truncate"><?= $h((string)($it['title'] ?? '—')) ?></div>
            <?php endif; ?>
            <div class="text-secondary small text-truncate">
              <i class="bi bi-link-45deg me-1"></i><?= $h($slug) ?>
            </div>
          </td>

          <td>
            <span class="small" title="<?= $h((string)($it['created_at_raw'] ?? '')) ?>">
              <?= $h((string)($it['created_at_display'] ?? ($it['created_at_raw'] ?? ''))) ?>
            </span>
          </td>

          <td class="text-end">
            <a class="btn btn-light btn-sm border me-1"
               href="<?= $h('admin.php?' . http_build_query(['r' => 'posts', 'a' => 'edit', 'id' => $it['id'], 'type' => $type])) ?>"
               aria-label="Upravit" data-bs-toggle="tooltip" data-bs-title="Upravit">
              <i class="bi bi-pencil"></i>
            </a>

            <form method="post" action="<?= $h('admin.php?' . http_build_query(['r' => 'posts', 'a' => 'toggle', 'type' => $type])) ?>" class="d-inline" data-ajax>
              <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
              <input type="hidden" name="id" value="<?= $h((string)$it['id']) ?>">
              <button class="btn btn-light btn-sm border me-1" type="submit"
                      aria-label="<?= $isPublished ? 'Zneviditelnit' : 'Publikovat' ?>"
                      data-bs-toggle="tooltip" data-bs-title="<?= $isPublished ? 'Zneviditelnit' : 'Publikovat' ?>">
                <?php if ($isPublished): ?>
                  <i class="bi bi-eye"></i>
                <?php else: ?>
                  <i class="bi bi-eye-slash"></i>
                <?php endif; ?>
              </button>
            </form>

            <form method="post"
                  action="<?= $h('admin.php?' . http_build_query(['r' => 'posts', 'a' => 'delete', 'type' => $type])) ?>"
                  class="d-inline"
                  data-ajax
                  data-confirm-modal="Opravdu smazat?"
                  data-confirm-modal-title="Potvrzení smazání"
                  data-confirm-modal-confirm-label="Smazat"
                  data-confirm-modal-cancel-label="Zrušit">
              <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
              <input type="hidden" name="id" value="<?= $h((string)$it['id']) ?>">
              <button class="btn btn-light btn-sm border"
                      type="submit" aria-label="Smazat"
                      data-bs-toggle="tooltip" data-bs-title="Smazat">
                <i class="bi bi-trash"></i>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>

      <?php if ($items === []): ?>
        <tr>
          <td colspan="4" class="text-center text-secondary py-4">
            <i class="bi bi-inbox me-1"></i>Žádné položky
          </td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
