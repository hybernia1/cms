<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array<int,array<string,mixed>> $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array<string,mixed> $campaign */
/** @var string $backUrl */

$this->render('parts/layouts/base', compact('pageTitle', 'nav', 'currentUser', 'flash'), function () use ($campaign, $backUrl) {
    $h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $subject = (string)($campaign['subject'] ?? '');
    $createdDisplay = (string)($campaign['created_at_display'] ?? ($campaign['created_at_raw'] ?? ''));
    $createdIso = (string)($campaign['created_at_iso'] ?? '');
    $authorName = (string)($campaign['author_name'] ?? '');
    $authorEmail = (string)($campaign['author_email'] ?? '');
    $recipients = (int)($campaign['recipients_count'] ?? 0);
    $sent = (int)($campaign['sent_count'] ?? 0);
    $failed = (int)($campaign['failed_count'] ?? 0);
    $body = (string)($campaign['body'] ?? '');
?>
  <div class="d-flex justify-content-between align-items-center gap-2 mb-4">
    <a class="btn btn-outline-secondary btn-sm" href="<?= $h($backUrl) ?>">
      <i class="bi bi-arrow-left"></i>
      Zpět na přehled
    </a>
  </div>

  <div class="row g-4">
    <div class="col-12 col-lg-4">
      <div class="card h-100">
        <div class="card-header">
          <h2 class="h6 mb-0">Informace o kampani</h2>
        </div>
        <div class="card-body">
          <dl class="mb-0">
            <dt class="text-secondary small">Předmět</dt>
            <dd class="fw-semibold"><?= $h($subject) ?></dd>

            <dt class="text-secondary small">Vytvořeno</dt>
            <dd>
              <time datetime="<?= $h($createdIso) ?>"><?= $h($createdDisplay) ?></time>
            </dd>

            <dt class="text-secondary small">Autor</dt>
            <dd>
              <?php if ($authorName !== ''): ?>
                <?= $h($authorName) ?><?php if ($authorEmail !== ''): ?>
                  <div class="text-secondary small"><?= $h($authorEmail) ?></div>
                <?php endif; ?>
              <?php else: ?>
                <span class="text-secondary">Neznámý</span>
              <?php endif; ?>
            </dd>

            <dt class="text-secondary small">Statistiky</dt>
            <dd>
              <div>Adresátů: <?= $recipients ?></div>
              <div>Úspěšně odesláno: <?= $sent ?></div>
              <div>Chyby: <?= $failed ?></div>
            </dd>
          </dl>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-8">
      <div class="card h-100">
        <div class="card-header">
          <h2 class="h6 mb-0">Náhled e-mailu</h2>
        </div>
        <div class="card-body">
          <?php if ($body !== ''): ?>
            <div class="border rounded p-3 bg-light-subtle" data-newsletter-campaign-preview>
              <?= $body ?>
            </div>
          <?php else: ?>
            <p class="text-secondary mb-0">Kampaň neobsahuje žádný obsah.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
<?php
});
