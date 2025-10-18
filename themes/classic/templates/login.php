<?php
/** @var string $csrfPublic */
/** @var string|null $type */
/** @var string|null $msg */
/** @var \Cms\Utils\LinkGenerator $urls */

$this->part('auth', 'card', [
    'title' => 'Přihlášení',
    'type'  => $type ?? null,
    'msg'   => $msg ?? null,
    'body'  => function () use ($urls, $csrfPublic): void {
        ?>
        <form class="form-grid" method="post" action="<?= e($urls->login()) ?>">
          <label class="form-field">
            <span class="form-field__label">E-mail</span>
            <input class="form-field__control" type="email" name="email" autocomplete="email" required>
          </label>
          <label class="form-field">
            <span class="form-field__label">Heslo</span>
            <input class="form-field__control" type="password" name="password" autocomplete="current-password" required>
          </label>
          <input type="hidden" name="csrf" value="<?= e($csrfPublic) ?>">
          <div class="form-actions">
            <button class="btn btn--primary">Přihlásit</button>
            <a class="btn btn--ghost" href="<?= e($urls->lost()) ?>">Zapomenuté heslo</a>
          </div>
        </form>
        <?php
    },
]);
