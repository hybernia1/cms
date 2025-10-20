<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var string $csrf */
/** @var string|null $error */
/** @var string|null $email */
/** @var bool $remember */

$flashPayload = null;
if (!empty($error)) {
    $flashPayload = ['type' => 'danger', 'msg' => (string)$error];
}

$remember = !empty($remember);
$emailValue = isset($email) ? (string)$email : '';

$this->render('layouts/auth', ['pageTitle' => $pageTitle ?? 'Přihlášení', 'flash' => $flashPayload], function () use ($csrf, $remember, $emailValue) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $emailSanitized = $h($emailValue);
?>
  <form method="post" action="admin.php?r=auth&a=login" novalidate autocomplete="off" data-ajax>
    <div class="mb-3">
      <label class="form-label">E-mail</label>
      <input class="form-control" type="email" name="email" value="<?= $emailSanitized ?>" required autofocus>
    </div>
    <div class="mb-3">
      <label class="form-label">Heslo</label>
      <input class="form-control" type="password" name="password" required minlength="8">
    </div>
    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" name="remember" value="1" id="login-remember"<?= $remember ? ' checked' : '' ?>>
      <label class="form-check-label" for="login-remember">Zapamatovat přihlášení</label>
    </div>
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
    <div class="d-grid gap-2">
      <button class="btn btn-primary" type="submit">Přihlásit</button>
      <a class="btn btn-outline-secondary" href="./">Zpět na web</a>
    </div>
  </form>
<?php
});
