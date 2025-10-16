<?php
/** @var \Cms\View\Assets $assets */
/** @var string $siteTitle */
/** @var array<int,array> $latestPosts */
$this->render('layouts/base', compact('assets','siteTitle'), function() use ($latestPosts) {
?>
  <div class="card">
    <h2 style="margin-top:0">Poslední příspěvky</h2>
    <?php if (!$latestPosts): ?>
      <p class="meta">Zatím žádné příspěvky.</p>
    <?php else: ?>
      <ul>
        <?php foreach ($latestPosts as $p): ?>
          <li>
            <a href="./post/<?= htmlspecialchars((string)$p['slug'], ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars((string)$p['title'], ENT_QUOTES, 'UTF-8') ?>
            </a>
            <span class="meta"> – <?= htmlspecialchars((string)$p['created_at'], ENT_QUOTES, 'UTF-8') ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
<?php }); ?>
