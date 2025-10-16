<?php
/** @var array<int,array<string,mixed>> $posts */
/** @var string|null $emptyMessage */
/** @var \Cms\Utils\LinkGenerator $urls */
/** @var bool|null $showType */

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$showType = $showType ?? false;
$message = $emptyMessage ?? 'Nic zde zatím není.';
?>
<?php if (!$posts): ?>
  <p class="muted"><?= $h($message) ?></p>
<?php else: ?>
  <ul class="post-list">
    <?php foreach ($posts as $post):
      $type = (string)($post['type'] ?? 'post');
      $slug = (string)($post['slug'] ?? '');
      $url  = $type === 'page' ? $urls->page($slug) : $urls->post($slug);
      $created = (string)($post['created_at'] ?? '');
      ?>
      <li class="post-list__item">
        <a class="post-list__title" href="<?= $h($url) ?>"><?= $h((string)($post['title'] ?? 'Bez názvu')) ?></a>
        <div class="post-list__meta">
          <span><?= $h($created) ?></span>
          <?php if ($showType): ?>
            <span class="post-list__badge"><?= $h($type === 'post' ? 'Článek' : ucfirst($type)) ?></span>
          <?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
