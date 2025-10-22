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
<div class="modal fade" id="newsletterCampaignScheduleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form
      class="modal-content"
      method="post"
      action="admin.php?r=newsletter-campaigns&a=schedule"
      data-ajax
      data-newsletter-campaigns-schedule-form
    >
      <div class="modal-header">
        <h5 class="modal-title">Plán kampaně</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zavřít"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="newsletter-campaign-schedule-start">Začátek</label>
          <input
            class="form-control"
            type="datetime-local"
            id="newsletter-campaign-schedule-start"
            name="start_at"
            step="60"
          >
        </div>
        <div class="mb-3">
          <label class="form-label" for="newsletter-campaign-schedule-end">Konec</label>
          <input
            class="form-control"
            type="datetime-local"
            id="newsletter-campaign-schedule-end"
            name="end_at"
            step="60"
          >
          <div class="form-text">Pokud není vyplněno, plán skončí po dokončení všech pokusů.</div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="newsletter-campaign-schedule-interval">Interval (minuty)</label>
            <input
              class="form-control"
              type="number"
              min="0"
              step="1"
              id="newsletter-campaign-schedule-interval"
              name="interval_minutes"
              value="0"
            >
          </div>
          <div class="col-md-6">
            <label class="form-label" for="newsletter-campaign-schedule-attempts">Maximální pokusy</label>
            <input
              class="form-control"
              type="number"
              min="1"
              step="1"
              id="newsletter-campaign-schedule-attempts"
              name="max_attempts"
              value="1"
            >
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zavřít</button>
        <button type="submit" class="btn btn-primary">Uložit plán</button>
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <input type="hidden" name="id" value="">
        <input type="hidden" name="q" value="<?= $h((string)$filters['q']) ?>">
        <input type="hidden" name="author" value="<?= (int)$filters['author'] ?>">
        <input type="hidden" name="page" value="<?= $page ?>">
      </div>
    </form>
  </div>
</div>
