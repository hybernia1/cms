<?php
/** @var string $email */

$this->part('auth', 'card', [
    'title' => 'Registrace dokončena',
    'type'  => 'success',
    'msg'   => null,
    'body'  => static function () use ($email): void {
        ?>
        <p>Děkujeme za registraci. Na adresu <strong><?= e($email) ?></strong> jsme odeslali potvrzení.</p>
        <p class="muted">Můžete se nyní přihlásit a začít tvořit obsah.</p>
        <?php
    },
]);
