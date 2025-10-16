<?php
declare(strict_types=1);
/** @var \Cms\View\Assets $assets */
/** @var string $siteTitle */
/** @var string $email */
/** @var array|null $frontUser */
/** @var array<int,array<string,mixed>> $navigation */
$this->render('layouts/base', [
  'assets'     => $assets,
  'siteTitle'  => $siteTitle,
  'frontUser'  => $frontUser ?? null,
  'navigation' => $navigation ?? [],
], function() use ($email) {
  $h = fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
?>
<div class="alert alert-success">Účet byl vytvořen. Můžete se přihlásit jako <strong><?= $h($email) ?></strong>.</div>
<?php }); ?>
