<?php
declare(strict_types=1);
/** @var array<int,array> $commentsTree */

$h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$renderNode = function(array $node) use (&$renderNode, $h): void {
  ?>
  <div class="card mb-2">
    <div class="card-body">
      <div class="d-flex justify-content-between">
        <div class="fw-semibold"><?= $h((string)($node['author_name'] ?? '')) ?></div>
        <div class="small text-secondary"><?= $h((string)$node['created_at']) ?></div>
      </div>
      <div class="mt-2" style="white-space:pre-wrap"><?= nl2br($h((string)$node['content'])) ?></div>
    </div>
  </div>
  <?php
  if (!empty($node['children'])) {
    echo '<div class="ms-3">';
    foreach ($node['children'] as $child) $renderNode($child);
    echo '</div>';
  }
};

if (!$commentsTree): ?>
  <div class="card mb-3"><div class="card-body"><span class="text-secondary">Zatím žádné komentáře.</span></div></div>
<?php else: ?>
  <?php foreach ($commentsTree as $n) $renderNode($n); ?>
<?php endif; ?>
