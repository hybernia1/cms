<?php
declare(strict_types=1);
/** @var array<int,array<string,mixed>> $commentsTree */

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$renderNode = function(array $node) use (&$renderNode, $h): void {
    ?>
    <article class="comment">
      <header class="comment__header">
        <span class="comment__author"><?= $h((string)($node['author_name'] ?? '')) ?></span>
        <span class="comment__meta"><?= $h((string)$node['created_at']) ?></span>
      </header>
      <div class="comment__body"><?= nl2br($h((string)($node['content'] ?? ''))) ?></div>
      <?php if (!empty($node['children'])): ?>
        <div class="comment__children">
          <?php foreach ($node['children'] as $child): ?>
            <?php $renderNode($child); ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </article>
    <?php
};
?>
<div class="comments">
  <?php if (!$commentsTree): ?>
    <p class="muted">Zatím žádné komentáře.</p>
  <?php else: ?>
    <?php foreach ($commentsTree as $comment): ?>
      <?php $renderNode($comment); ?>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
