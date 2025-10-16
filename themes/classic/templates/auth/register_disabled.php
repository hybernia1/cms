<?php
/** @var \Cms\View\Assets $assets */
/** @var string $siteTitle */

$this->render('layouts/base', compact('assets', 'siteTitle'), function() {
?>
<div class="alert alert-warning">Registrace je dočasně vypnutá.</div>
<?php }); ?>
