<?php
declare(strict_types=1);
/** @var \Cms\View\Assets $assets */
/** @var string $siteTitle */
/** @var string $csrfPublic */
/** @var string|null $type */
/** @var string|null $msg */
/** @var \Cms\Utils\LinkGenerator $urls */

$type = $type ?? null;
$msg  = $msg ?? null;

$this->render('layouts/base', compact('assets', 'siteTitle'), function() use ($csrfPublic, $type, $msg, $urls) {
  $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<div class="card">
  <div class="card-header">Přihlášení</div>
  <div class="card-body">
    <?php if (!empty($type)): ?><div class="alert alert-<?= $h($type) ?>"><?= $h((string)$msg) ?></div><?php endif; ?>
    <form method="post" action="<?= $h($urls->login()) ?>">
      <div class="mb-3">
        <label class="form-label">E-mail</label>
        <input class="form-control" type="email" name="email" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Heslo</label>
        <input class="form-control" type="password" name="password" required>
      </div>
      <input type="hidden" name="csrf" value="<?= $h($csrfPublic) ?>">
      <div class="d-flex gap-2">
        <button class="btn btn-primary">Přihlásit</button>
        <a class="btn btn-link" href="<?= $h($urls->lost()) ?>">Zapomenuté heslo</a>
      </div>
    </form>
  </div>
</div>
<?php }); ?>
