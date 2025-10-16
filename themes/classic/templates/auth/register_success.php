<?php
/** @var \Cms\View\Assets $assets */
/** @var string $siteTitle */
/** @var string $email */

$this->render('layouts/base', compact('assets', 'siteTitle'), function() use ($email) {
  $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<div class="alert alert-success">Účet byl vytvořen. Můžete se přihlásit jako <?= $h($email) ?>.</div>
<?php }); ?>
