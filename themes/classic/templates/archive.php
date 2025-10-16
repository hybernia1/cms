<?php
/** @var \Cms\View\Assets $assets */
/** @var string $siteTitle */
/** @var array<int,array> $items */
/** @var string|null $type */
/** @var \Cms\Utils\LinkGenerator $urls */
$this->render('layouts/base', compact('assets', 'siteTitle'), function() use ($items, $type, $urls) {
?>
  <div class="card">
    <h2 style="margin-top:0">Archiv<?= $type ? ' – ' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') : '' ?></h2>
    <?php if (!$items): ?>
      <p class="meta">Nic k zobrazení.</p>
    <?php else: ?>
      <ul>
        <?php foreach ($items as $p): ?>
          <li>
            <a href="<?= htmlspecialchars($urls->post((string)$p['slug']), ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars((string)$p['title'], ENT_QUOTES, 'UTF-8') ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
<?php }); ?>
