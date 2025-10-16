<?php
/** @var \Cms\View\Assets $assets */
/** @var string $siteTitle */

$this->render('layouts/base', compact('assets', 'siteTitle'), function() {
?>
<div class="alert alert-info">Pokud účet existuje, poslali jsme odkaz pro reset hesla.</div>
<?php }); ?>
