<?php
declare(strict_types=1);
/** @var array<int,array<string,mixed>> $children */

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div data-comment-thread>
  <?php if ($children): ?>
    <div class="card">
      <div class="card-header">Odpovědi (<?= count($children) ?>)</div>
      <div class="list-group list-group-flush">
        <?php foreach ($children as $child):
          $created = (string)($child['created_at_display'] ?? ($child['created_at'] ?? ''));
        ?>
          <div class="list-group-item">
            <div class="d-flex justify-content-between">
              <div class="fw-semibold text-truncate"><?= $h((string)($child['author_name'] ?? '')) ?></div>
              <div class="small text-secondary text-nowrap ms-3"><?= $h($created) ?></div>
            </div>
            <div style="white-space:pre-wrap">
              <?= nl2br($h((string)($child['content'] ?? ''))) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
