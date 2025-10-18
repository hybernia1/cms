<?php
/** @var string $email */
/** @var bool $pendingApproval */

$pending = !empty($pendingApproval);

$this->part('auth', 'card', [
    'title' => $pending ? 'Registrace odeslána' : 'Registrace dokončena',
    'type'  => 'success',
    'msg'   => null,
    'body'  => static function () use ($email, $pending): void {
        ?>
        <?php if ($pending): ?>
          <p>Děkujeme za registraci. Na adresu <strong><?= e($email) ?></strong> jsme odeslali informaci o tom, že pracujeme na schválení vaší žádosti.</p>
          <p class="muted">Jakmile administrátor účet aktivuje, přijde vám potvrzující e-mail.</p>
        <?php else: ?>
          <p>Registrace proběhla úspěšně a jste automaticky přihlášeni.</p>
          <p class="muted">Na adresu <strong><?= e($email) ?></strong> jsme odeslali e-mail s přístupovými informacemi.</p>
        <?php endif; ?>
        <?php
    },
]);
