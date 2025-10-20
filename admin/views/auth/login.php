<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var string $csrf */
/** @var string|null $error */
/** @var string|null $email */
/** @var bool $remember */
/** @var array<string,mixed>|null $errors */

$flashPayload = null;
if (!empty($error)) {
    $flashPayload = ['type' => 'danger', 'msg' => (string)$error];
}

$remember = !empty($remember);
$emailValue = isset($email) ? (string)$email : '';
$errors = is_array($errors ?? null) ? $errors : [];
$extractErrorMessage = static function (string $key) use ($errors): string {
    if (!array_key_exists($key, $errors)) {
        return '';
    }

    $value = $errors[$key];
    if (is_array($value)) {
        foreach ($value as $item) {
            if ($item === null) {
                continue;
            }
            $string = trim((string)$item);
            if ($string !== '') {
                return $string;
            }
        }

        return '';
    }

    return trim((string)$value);
};
$emailError = $extractErrorMessage('email');
$passwordError = $extractErrorMessage('password');
$formError = $extractErrorMessage('form');

$this->render('layouts/auth', ['pageTitle' => $pageTitle ?? 'Přihlášení', 'flash' => $flashPayload], function () use ($csrf, $remember, $emailValue, $emailError, $passwordError, $formError) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $emailSanitized = $h($emailValue);
?>
  <form method="post" action="admin.php?r=auth&a=login" novalidate autocomplete="off" data-ajax data-form-helper="validation">
    <div class="invalid-feedback d-block mb-3" data-error-for="form" id="login-form-error"<?= $formError === '' ? ' hidden' : '' ?>>
      <?= $formError === '' ? '' : $h($formError) ?>
    </div>
    <div class="mb-3">
      <label class="form-label">E-mail</label>
      <input class="form-control<?= $emailError === '' ? '' : ' is-invalid' ?>" type="email" name="email" value="<?= $emailSanitized ?>" required autofocus<?= $emailError === '' ? '' : ' aria-describedby="login-email-error"' ?>>
      <div class="invalid-feedback" data-error-for="email" id="login-email-error"<?= $emailError === '' ? ' hidden' : '' ?>>
        <?= $emailError === '' ? '' : $h($emailError) ?>
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label">Heslo</label>
      <input class="form-control<?= $passwordError === '' ? '' : ' is-invalid' ?>" type="password" name="password" required minlength="8" autocomplete="current-password"<?= $passwordError === '' ? '' : ' aria-describedby="login-password-error"' ?>>
      <div class="invalid-feedback" data-error-for="password" id="login-password-error"<?= $passwordError === '' ? ' hidden' : '' ?>>
        <?= $passwordError === '' ? '' : $h($passwordError) ?>
      </div>
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
