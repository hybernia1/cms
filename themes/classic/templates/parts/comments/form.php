<?php
declare(strict_types=1);
/** @var int $postId */
/** @var string $csrfPublic */
/** @var array|null $commentFlash */
/** @var array|null $frontUser */
/** @var \Cms\Utils\LinkGenerator $urls */

$h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<div class="card">
  <div class="card-header">Přidat komentář</div>
  <div class="card-body">
    <?php if (!empty($commentFlash)): ?>
      <div class="alert alert-<?= $h((string)$commentFlash['type']) ?> mb-3"><?= $h((string)$commentFlash['msg']) ?></div>
    <?php endif; ?>

    <!-- Odesíláme přes LinkGenerator, aby fungoval fallback bez mod_rewrite -->
    <form method="post" action="<?= $h($urls->commentAction()) ?>" autocomplete="off">
      <?php if ($frontUser): ?>
        <div class="mb-3">
          <span class="badge text-bg-success">Přihlášen jako</span>
          <strong><?= $h((string)($frontUser['name'] ?? 'Uživatel')) ?></strong>
          <span class="text-secondary small"><?= $h((string)($frontUser['email'] ?? '')) ?></span>
        </div>
      <?php else: ?>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Jméno</label>
            <input class="form-control" name="author_name" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">E-mail <span class="text-secondary">(nepovinné)</span></label>
            <input class="form-control" type="email" name="author_email">
          </div>
        </div>
      <?php endif; ?>

      <div class="mb-3 mt-3">
        <label class="form-label">Komentář</label>
        <textarea class="form-control" name="content" rows="5" required></textarea>
      </div>

      <!-- Honeypot -->
      <div style="position:absolute;left:-9999px;top:-9999px" aria-hidden="true">
        <label>Website</label>
        <input type="text" name="website" tabindex="-1" autocomplete="off">
      </div>

      <input type="hidden" name="post_id" value="<?= (int)$postId ?>">
      <input type="hidden" name="parent_id" value="0">
      <input type="hidden" name="csrf" value="<?= $h($csrfPublic) ?>">

      <button class="btn btn-primary" type="submit">Odeslat</button>
      <span class="small text-secondary ms-2">Komentář půjde ke schválení.</span>
    </form>
  </div>
</div>
