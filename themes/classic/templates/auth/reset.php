<?php
declare(strict_types=1);
/** @var \Cms\View\Assets $assets */
/** @var string $siteTitle */
/** @var string $csrfPublic */
/** @var string $token */

$this->render('layouts/base', compact('assets', 'siteTitle'), function() use ($csrfPublic, $token) {
  $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<div class="card">
  <div class="card-header">Nastavit nové heslo</div>
  <div class="card-body">
    <form method="post" action="./?r=reset">
      <div class="mb-3"><label class="form-label">Nové heslo</label><input class="form-control" type="password" name="password" required></div>
      <div class="mb-3"><label class="form-label">Znovu</label><input class="form-control" type="password" name="password2" required></div>
      <input type="hidden" name="token" value="<?= $h($token) ?>">
      <input type="hidden" name="csrf" value="<?= $h($csrfPublic) ?>">
      <button class="btn btn-primary">Uložit</button>
    </form>
  </div>
</div>
<?php }); ?>
