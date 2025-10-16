<?php
/** @var string $csrfPublic */
/** @var string|null $type */
/** @var string|null $msg */
/** @var \Cms\Utils\LinkGenerator $urls */

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

ob_start();
?>
<form class="form-grid" method="post" action="<?= $h($urls->login()) ?>">
  <label class="form-field">
    <span class="form-field__label">E-mail</span>
    <input class="form-field__control" type="email" name="email" autocomplete="email" required>
  </label>
  <label class="form-field">
    <span class="form-field__label">Heslo</span>
    <input class="form-field__control" type="password" name="password" autocomplete="current-password" required>
  </label>
  <input type="hidden" name="csrf" value="<?= $h($csrfPublic) ?>">
  <div class="form-actions">
    <button class="btn btn--primary">Přihlásit</button>
    <a class="btn btn--ghost" href="<?= $h($urls->lost()) ?>">Zapomenuté heslo</a>
  </div>
</form>
<?php
$body = ob_get_clean();

$this->part('parts/auth/card', [
    'title' => 'Přihlášení',
    'type'  => $type ?? null,
    'msg'   => $msg ?? null,
    'body'  => $body,
]);
