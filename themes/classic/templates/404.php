<?php
/** @var \Cms\View\Assets $assets */
/** @var string $siteTitle */
$this->render('layouts/base', compact('assets', 'siteTitle'), function() {
?>
  <div class="card">
    <h2 style="margin-top:0">404 – Stránka nenalezena</h2>
    <p class="meta">Zkuste se vrátit na <a href="./">homepage</a>.</p>
  </div>
<?php }); ?>
