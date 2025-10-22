<?php
declare(strict_types=1);

/**
 * @var array<int,array<string,mixed>> $items
 * @var string $csrf
 * @var array{q:string,author:int} $filters
 * @var int $page
 */

$items = is_array($items ?? null) ? $items : [];
$csrf = (string)($csrf ?? '');
$filters = array_merge(['q' => '', 'author' => 0], $filters ?? []);
$page = max(1, (int)($page ?? 1));

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div class="card" data-newsletter-campaigns-table-wrapper>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Předmět</th>
          <th style="width:200px">Odeslání</th>
          <th style="width:200px">Autor</th>
          <th style="width:180px">Vytvořeno</th>
          <th style="width:220px" class="text-end">Akce</th>
        </tr>
      </thead>
      <tbody data-newsletter-campaigns-tbody>
        <?php foreach ($items as $item): ?>
          <?php
            $id = (int)($item['id'] ?? 0);
            $subject = (string)($item['subject'] ?? '');
            $recipients = (int)($item['recipients_count'] ?? 0);
            $sent = (int)($item['sent_count'] ?? 0);
            $failed = (int)($item['failed_count'] ?? 0);
            $authorName = trim((string)($item['author_name'] ?? ''));
            $authorEmail = trim((string)($item['author_email'] ?? ''));
            $createdDisplay = (string)($item['created_at_display'] ?? '');
            $createdIso = (string)($item['created_at_iso'] ?? '');
            $detailUrl = (string)($item['detail_url'] ?? 'admin.php?r=newsletter-campaigns');
            $payload = [
                'id'      => $id,
                'subject' => $subject,
                'body'    => (string)($item['body'] ?? ''),
            ];
          ?>
          <tr data-newsletter-campaign-row data-campaign-id="<?= $id ?>">
            <td>
              <div class="fw-semibold text-truncate" title="<?= $h($subject) ?>">
                <?= $h($subject) ?>
              </div>
            </td>
            <td>
              <div class="text-secondary small">Adresátů: <?= $recipients ?></div>
              <div class="text-secondary small">Úspěšně: <?= $sent ?>, Chyby: <?= $failed ?></div>
            </td>
            <td>
              <?php if ($authorName !== ''): ?>
                <div class="text-truncate" title="<?= $h($authorName) ?>">
                  <?= $h($authorName) ?>
                </div>
              <?php else: ?>
                <span class="text-secondary">Neznámý</span>
              <?php endif; ?>
              <?php if ($authorEmail !== ''): ?>
                <div class="text-secondary small text-truncate" title="<?= $h($authorEmail) ?>">
                  <?= $h($authorEmail) ?>
                </div>
              <?php endif; ?>
            </td>
            <td>
              <time class="text-secondary small" datetime="<?= $h($createdIso) ?>">
                <?= $h($createdDisplay !== '' ? $createdDisplay : (string)($item['created_at_raw'] ?? '')) ?>
              </time>
            </td>
            <td class="text-end">
              <a class="btn btn-light btn-sm border me-1" href="<?= $h($detailUrl) ?>" data-bs-toggle="tooltip" data-bs-title="Detail">
                <i class="bi bi-search"></i>
              </a>
              <button
                class="btn btn-light btn-sm border me-1"
                type="button"
                data-newsletter-campaign-edit-trigger
                data-campaign-id="<?= $id ?>"
                data-bs-toggle="tooltip"
                data-bs-title="Upravit"
              >
                <i class="bi bi-pencil"></i>
              </button>
              <?php $this->render('parts/forms/confirm-action', [
                'action' => 'admin.php?r=newsletter-campaigns&a=duplicate',
                'csrf'   => $csrf,
                'hidden' => [
                  'id'     => $id,
                  'q'      => $filters['q'],
                  'author' => $filters['author'],
                  'page'   => $page,
                ],
                'button' => [
                  'icon'      => 'bi bi-files',
                  'tooltip'   => 'Duplikovat',
                  'ariaLabel' => 'Duplikovat',
                ],
                'confirm' => [
                  'message' => 'Duplikovat tuto kampaň?',
                  'title'   => 'Duplikace kampaně',
                  'confirm' => 'Duplikovat',
                  'cancel'  => 'Zrušit',
                ],
              ]); ?>
              <?php $this->render('parts/forms/confirm-action', [
                'action' => 'admin.php?r=newsletter-campaigns&a=delete',
                'csrf'   => $csrf,
                'hidden' => [
                  'id'     => $id,
                  'q'      => $filters['q'],
                  'author' => $filters['author'],
                  'page'   => $page,
                ],
                'button' => [
                  'icon'      => 'bi bi-trash',
                  'tooltip'   => 'Smazat',
                  'ariaLabel' => 'Smazat',
                ],
                'confirm' => [
                  'message' => 'Opravdu chcete kampaň smazat?',
                  'title'   => 'Potvrzení smazání',
                  'confirm' => 'Smazat',
                  'cancel'  => 'Zrušit',
                ],
              ]); ?>
              <script
                type="application/json"
                data-newsletter-campaign-data
                data-campaign-id="<?= $id ?>"
              ><?= json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if ($items === []): ?>
          <tr data-newsletter-campaigns-empty-row>
            <td colspan="5" class="text-center text-secondary py-4">
              <i class="bi bi-inbox me-1"></i>Žádné kampaně
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
