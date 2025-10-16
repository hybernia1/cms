<?php
declare(strict_types=1);
/** @var \Cms\View\Assets $assets */
/** @var string $siteTitle */
/** @var string $csrfPublic */

$this->render('layouts/base', compact('assets', 'siteTitle'), function() use ($csrfPublic) {
  $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<div class="card">
  <div class="card-header">Obnova hesla</div>
  <div class="card-body">
    <form method="post" action="./?r=lost">
      <div class="mb-3"><label class="form-label">E-mail</label><input class="form-control" type="email" name="email" required></div>
      <input type="hidden" name="csrf" value="<?= $h($csrfPublic) ?>">
      <button class="btn btn-primary">Odeslat odkaz</button>
    </form>
  </div>
</div>
<?php }); ?>
