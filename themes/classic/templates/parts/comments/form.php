<?php
declare(strict_types=1);
/** @var int $postId */
/** @var string $csrfPublic */
/** @var array|null $commentFlash */
/** @var array|null $frontUser */
/** @var \Cms\Utils\LinkGenerator $urls */

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<section class="card card--form">
  <h3 class="card__title">Přidat komentář</h3>
  <?php if (!empty($commentFlash)): ?>
    <div class="alert alert--<?= $h((string)$commentFlash['type']) ?>"><?= $h((string)$commentFlash['msg']) ?></div>
  <?php endif; ?>

  <form method="post" action="<?= $h($urls->commentAction()) ?>" class="form-grid" autocomplete="off">
    <?php if ($frontUser): ?>
      <p class="form-note">Přihlášen jako <strong><?= $h((string)($frontUser['name'] ?? 'Uživatel')) ?></strong> <?= $h((string)($frontUser['email'] ?? '')) ?></p>
    <?php else: ?>
      <label class="form-field">
        <span class="form-field__label">Jméno</span>
        <input class="form-field__control" name="author_name" required>
      </label>
      <label class="form-field">
        <span class="form-field__label">E-mail <span class="muted">(nepovinné)</span></span>
        <input class="form-field__control" type="email" name="author_email">
      </label>
    <?php endif; ?>

    <label class="form-field form-field--full">
      <span class="form-field__label">Komentář</span>
      <textarea class="form-field__control" name="content" rows="5" required></textarea>
    </label>

    <div class="sr-only" aria-hidden="true">
      <label>Website</label>
      <input type="text" name="website" tabindex="-1" autocomplete="off">
    </div>

    <input type="hidden" name="post_id" value="<?= (int)$postId ?>">
    <input type="hidden" name="parent_id" value="0">
    <input type="hidden" name="csrf" value="<?= $h($csrfPublic) ?>">

    <div class="form-actions">
      <button class="btn btn--primary" type="submit">Odeslat</button>
      <span class="form-hint">Komentář bude nejprve schválen.</span>
    </div>
  </form>
</section>
