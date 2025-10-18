<?php
declare(strict_types=1);
/** @var array<int,array<string,mixed>> $commentsTree */
/** @var array<string,string>|null $classes */
/** @var array<string,string>|null $strings */
/** @var string|null $threadId */

$commentsTree = $commentsTree ?? [];
$classes = is_array($classes ?? null) ? $classes : [];
$strings = is_array($strings ?? null) ? $strings : [];
$threadId = isset($threadId) ? (string)$threadId : '';

$defaults = [
    'wrapper'      => 'comments',
    'empty'        => 'comments__empty',
    'item'         => 'comment',
    'header'       => 'comment__header',
    'author'       => 'comment__author',
    'meta'         => 'comment__meta',
    'content'      => 'comment__body',
    'children'     => 'comment__children',
    'actions'      => 'comment__actions',
    'replyButton'  => 'comment__reply',
];
$classes = $classes + $defaults;

$textDefaults = [
    'empty' => 'Zatím žádné komentáře.',
    'reply' => 'Odpovědět',
];
$strings = $strings + $textDefaults;

$esc = static fn(string $value): string => e($value);
$cls = static fn(string $key) => trim((string)($classes[$key] ?? ''));
$replyLabel = (string)($strings['reply'] ?? 'Odpovědět');
$emptyLabel = (string)($strings['empty'] ?? '');

$renderNode = static function (array $node) use (&$renderNode, $esc, $cls, $replyLabel, $threadId): void {
    $id        = (int)($node['id'] ?? 0);
    $authorRaw = (string)($node['author_name'] ?? '');
    $meta      = (string)($node['created_at'] ?? '');
    $content   = (string)($node['content'] ?? '');
    $children  = is_array($node['children'] ?? null) ? $node['children'] : [];
    ?>
    <article class="<?= $esc($cls('item')) ?>" id="comment-<?= $id ?>" data-comment-id="<?= $id ?>">
      <header class="<?= $esc($cls('header')) ?>">
        <span class="<?= $esc($cls('author')) ?>"><?= $esc($authorRaw) ?></span>
        <?php if ($meta !== ''): ?>
          <span class="<?= $esc($cls('meta')) ?>"><?= $esc($meta) ?></span>
        <?php endif; ?>
      </header>
      <div class="<?= $esc($cls('content')) ?>"><?php echo nl2br($esc($content)); ?></div>
      <div class="<?= $esc($cls('actions')) ?>">
        <button
          type="button"
          class="<?= $esc($cls('replyButton')) ?>"
          data-comment-reply-trigger
          data-comment-id="<?= $id ?>"
          data-comment-author="<?= $esc($authorRaw) ?>"
          <?php if ($threadId !== ''): ?>data-comment-thread="<?= $esc($threadId) ?>"<?php endif; ?>
        ><?= $esc($replyLabel) ?></button>
      </div>
      <?php if ($children !== []): ?>
        <div class="<?= $esc($cls('children')) ?>">
          <?php foreach ($children as $child): ?>
            <?php if (is_array($child)) { $renderNode($child); } ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </article>
    <?php
};
?>
<div
  class="<?= $esc($cls('wrapper')) ?>"
  data-comment-list
  <?php if ($threadId !== ''): ?>data-comment-thread="<?= $esc($threadId) ?>"<?php endif; ?>
>
  <?php if ($commentsTree === []): ?>
    <?php if ($emptyLabel !== ''): ?><p class="<?= $esc($cls('empty')) ?>"><?= $esc($emptyLabel) ?></p><?php endif; ?>
  <?php else: ?>
    <?php foreach ($commentsTree as $comment): ?>
      <?php if (is_array($comment)) { $renderNode($comment); } ?>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
