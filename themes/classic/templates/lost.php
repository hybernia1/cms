<?php
/** @var string $csrfPublic */
/** @var string|null $type */
/** @var string|null $msg */
/** @var \Cms\Utils\LinkGenerator $urls */

$this->part('auth', 'card', [
    'title' => 'Zapomenuté heslo',
    'type'  => $type ?? null,
    'msg'   => $msg ?? null,
    'body'  => function () use ($urls, $csrfPublic): void {
        ?>
        <form class="form-grid" method="post" action="<?= e($urls->lost()) ?>">
          <label class="form-field">
            <span class="form-field__label">E-mail</span>
            <input class="form-field__control" type="email" name="email" autocomplete="email" required>
          </label>
          <input type="hidden" name="csrf" value="<?= e($csrfPublic) ?>">
          <div class="form-actions">
            <button class="btn btn--primary">Odeslat instrukce</button>
            <a class="btn btn--ghost" href="<?= e($urls->login()) ?>">Zpět na přihlášení</a>
          </div>
        </form>
        <?php
    },
]);
