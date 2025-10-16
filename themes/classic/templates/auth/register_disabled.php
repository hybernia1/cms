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
<div class="alert alert-warning">Registrace je aktuálně vypnutá.</div>
<?php }); ?>
