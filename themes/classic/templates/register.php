<?php
/** @var string $csrfPublic */
/** @var string|null $type */
/** @var string|null $msg */
/** @var bool $requiresApproval */
/** @var \Cms\Utils\LinkGenerator $urls */

$this->part('auth', 'card', [
    'title' => 'Registrace',
    'type'  => $type ?? null,
    'msg'   => $msg ?? null,
    'body'  => function () use ($urls, $csrfPublic, $requiresApproval): void {
        ?>
        <form class="form-grid" method="post" action="<?= e($urls->register()) ?>">
          <label class="form-field">
            <span class="form-field__label">Jméno</span>
            <input class="form-field__control" name="name" autocomplete="name" required>
          </label>
          <label class="form-field">
            <span class="form-field__label">E-mail</span>
            <input class="form-field__control" type="email" name="email" autocomplete="email" required>
          </label>
          <label class="form-field">
            <span class="form-field__label">Heslo</span>
            <input class="form-field__control" type="password" name="password" autocomplete="new-password" required>
          </label>
          <input type="hidden" name="csrf" value="<?= e($csrfPublic) ?>">
          <div class="form-actions">
            <button class="btn btn--primary">Registrovat</button>
            <a class="btn btn--ghost" href="<?= e($urls->login()) ?>">Mám účet</a>
          </div>
        </form>
        <p class="muted small mt-3">
          <?php if ($requiresApproval): ?>
            Po odeslání registrace počkejte na schválení administrátorem. Informaci vám zašleme e-mailem.
          <?php else: ?>
            Po registraci budete automaticky přihlášeni a obdržíte e-mail s přístupovými informacemi.
          <?php endif; ?>
        </p>
        <?php
    },
]);
