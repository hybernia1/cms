<?php
/** @var string $email */

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
ob_start();
?>
<p>Děkujeme za registraci. Na adresu <strong><?= $h($email) ?></strong> jsme odeslali potvrzení.</p>
<p class="muted">Můžete se nyní přihlásit a začít tvořit obsah.</p>
<?php
$body = ob_get_clean();
$this->part('auth', 'card', [
    'title' => 'Registrace dokončena',
    'type'  => 'success',
    'msg'   => null,
    'body'  => $body,
]);
