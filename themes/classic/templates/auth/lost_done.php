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
<div class="alert alert-info">Pokud účet existuje, poslali jsme odkaz pro reset hesla.</div>
<?php }); ?>
