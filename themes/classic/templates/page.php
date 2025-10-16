<?php
/** @var \Cms\View\Assets $assets */
/** @var string $siteTitle */
/** @var array $page */
$this->render('layouts/base', compact('assets', 'siteTitle'), function() use ($page) {
?>
  <article class="card">
    <h2 style="margin-top:0"><?= htmlspecialchars((string)$page['title'], ENT_QUOTES, 'UTF-8') ?></h2>
    <div style="margin-top:1rem;white-space:pre-wrap"><?= nl2br(htmlspecialchars((string)($page['content'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>
  </article>
<?php }); ?>
