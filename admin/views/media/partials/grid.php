<?php
declare(strict_types=1);
/** @var array<int,array<string,mixed>> $items */
/** @var string $csrf */
/** @var bool $webpEnabled */

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<?php if (!$items): ?>
  <div class="text-secondary" data-media-empty>
    <i class="bi bi-inbox me-1"></i>Žádná média.
  </div>
<?php else: ?>
  <div class="row g-3" data-media-grid-items>
    <?php foreach ($items as $item): ?>
      <?php $this->render('media/partials/card', [
        'item'        => $item,
        'csrf'        => $csrf,
        'webpEnabled' => $webpEnabled,
      ]); ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
