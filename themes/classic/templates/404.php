<?php
/** @var \Cms\View\Assets $assets */
/** @var string $siteTitle */
/** @var array|null $frontUser */
/** @var array<int,array<string,mixed>> $navigation */
$this->render('layouts/base', [
  'assets'     => $assets,
  'siteTitle'  => $siteTitle,
  'frontUser'  => $frontUser ?? null,
  'navigation' => $navigation ?? [],
], function() {
?>
  <div class="card">
    <h2 style="margin-top:0">404 – Stránka nenalezena</h2>
    <p class="meta">Zkuste se vrátit na <a href="./">homepage</a>.</p>
  </div>
<?php }); ?>
