<?php
declare(strict_types=1);

/**
 * @var string $csrf
 * @var array{q:string,author:int} $filters
 * @var int $page
 */

$csrf = (string)($csrf ?? '');
$filters = array_merge(['q' => '', 'author' => 0], $filters ?? []);
$page = max(1, (int)($page ?? 1));

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div class="modal fade" id="newsletterCampaignEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <form
      class="modal-content"
      method="post"
      action="admin.php?r=newsletter-campaigns&a=edit"
      data-ajax
      data-newsletter-campaigns-edit-form
    >
      <div class="modal-header">
        <h5 class="modal-title">Upravit kampaň</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zavřít"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="newsletter-campaign-edit-subject">Předmět</label>
          <input
            class="form-control"
            type="text"
            id="newsletter-campaign-edit-subject"
            name="subject"
            required
          >
        </div>
        <div class="mb-3">
          <label class="form-label" for="newsletter-campaign-edit-body">Obsah (HTML)</label>
          <textarea
            class="form-control"
            id="newsletter-campaign-edit-body"
            name="body"
            rows="8"
            required
          ></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zrušit</button>
        <button type="submit" class="btn btn-primary">Uložit změny</button>
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <input type="hidden" name="id" value="">
        <input type="hidden" name="q" value="<?= $h((string)$filters['q']) ?>">
        <input type="hidden" name="author" value="<?= (int)$filters['author'] ?>">
        <input type="hidden" name="page" value="<?= $page ?>">
      </div>
    </form>
  </div>
</div>
