<?php
/** @var string $csrfPublic */
/** @var string|null $type */
/** @var string|null $msg */
/** @var \Cms\Utils\LinkGenerator $urls */

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

ob_start();
?>
<form class="form-grid" method="post" action="<?= $h($urls->lost()) ?>">
  <label class="form-field">
    <span class="form-field__label">E-mail</span>
    <input class="form-field__control" type="email" name="email" autocomplete="email" required>
  </label>
  <input type="hidden" name="csrf" value="<?= $h($csrfPublic) ?>">
  <div class="form-actions">
    <button class="btn btn--primary">Odeslat instrukce</button>
    <a class="btn btn--ghost" href="<?= $h($urls->login()) ?>">Zpět na přihlášení</a>
  </div>
</form>
<?php
$body = ob_get_clean();

$this->part('parts/auth/card', [
    'title' => 'Zapomenuté heslo',
    'type'  => $type ?? null,
    'msg'   => $msg ?? null,
    'body'  => $body,
]);
