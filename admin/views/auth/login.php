<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var string $csrf */
/** @var string|null $error */

$flashPayload = null;
if (!empty($error)) {
    $flashPayload = ['type' => 'danger', 'msg' => (string)$error];
}

$this->render('layouts/auth', ['pageTitle' => $pageTitle ?? 'Přihlášení', 'flash' => $flashPayload], function () use ($csrf) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
  <form method="post" action="admin.php?r=auth&a=login" novalidate autocomplete="off" data-ajax>
    <div class="mb-3">
      <label class="form-label">E-mail</label>
      <input class="form-control" type="email" name="email" required autofocus>
    </div>
    <div class="mb-3">
      <label class="form-label">Heslo</label>
      <input class="form-control" type="password" name="password" required minlength="8">
    </div>
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
    <div class="d-grid gap-2">
      <button class="btn btn-primary" type="submit">Přihlásit</button>
      <a class="btn btn-outline-secondary" href="./">Zpět na web</a>
    </div>
  </form>
<?php
});
