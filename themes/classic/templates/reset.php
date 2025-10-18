<?php
/** @var string $csrfPublic */
/** @var string $token */
/** @var string|null $type */
/** @var string|null $msg */
/** @var \Cms\Utils\LinkGenerator $urls */

$this->part('auth', 'card', [
    'title' => 'Obnova hesla',
    'type'  => $type ?? null,
    'msg'   => $msg ?? null,
    'body'  => function () use ($urls, $csrfPublic, $token): void {
        ?>
        <form class="form-grid" method="post" action="<?= e($urls->reset()) ?>">
          <label class="form-field">
            <span class="form-field__label">Nové heslo</span>
            <input class="form-field__control" type="password" name="password" autocomplete="new-password" required>
          </label>
          <label class="form-field">
            <span class="form-field__label">Potvrzení hesla</span>
            <input class="form-field__control" type="password" name="password2" autocomplete="new-password" required>
          </label>
          <input type="hidden" name="token" value="<?= e($token) ?>">
          <input type="hidden" name="csrf" value="<?= e($csrfPublic) ?>">
          <div class="form-actions">
            <button class="btn btn--primary">Změnit heslo</button>
          </div>
        </form>
        <?php
    },
]);
