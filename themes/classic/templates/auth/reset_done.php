<?php
/** @var \Cms\View\Assets $assets */
/** @var string $siteTitle */

$this->render('layouts/base', compact('assets', 'siteTitle'), function() {
?>
<div class="alert alert-success">Heslo bylo změněno. Můžete se přihlásit.</div>
<?php }); ?>
