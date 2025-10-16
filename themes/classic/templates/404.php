<?php
/** @var \Cms\View\Assets $assets */
/** @var string $siteTitle */
/** @var \Cms\Utils\LinkGenerator $urls */
$this->render('layouts/base', compact('assets', 'siteTitle'), function() use ($urls) {
?>
  <div class="card">
    <h2 style="margin-top:0">404 – Stránka nenalezena</h2>
    <p class="meta">Zkuste se vrátit na <a href="<?= htmlspecialchars($urls->home(), ENT_QUOTES, 'UTF-8') ?>">homepage</a>.</p>
  </div>
<?php }); ?>
