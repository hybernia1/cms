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
<div class="alert alert-success">Heslo bylo změněno. Můžete se přihlásit.</div>
<?php }); ?>
