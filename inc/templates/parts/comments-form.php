<?php
declare(strict_types=1);
/** @var int $postId */
/** @var string $csrfPublic */
/** @var array<string,string>|null $commentFlash */
/** @var array<string,mixed>|null $frontUser */
/** @var \Cms\Utils\LinkGenerator|null $urls */
/** @var string|null $action */
/** @var array<string,string>|null $classes */
/** @var array<string,string>|null $strings */
/** @var string|null $threadId */

$postId       = isset($postId) ? (int)$postId : 0;
$csrfPublic   = (string)($csrfPublic ?? '');
$commentFlash = is_array($commentFlash ?? null) ? $commentFlash : null;
$frontUser    = is_array($frontUser ?? null) ? $frontUser : null;
$urls         = $urls ?? null;
$threadId     = isset($threadId) ? (string)$threadId : '';
$action       = isset($action) && $action !== ''
    ? (string)$action
    : ($urls ? $urls->commentAction() : './?r=comment');

$classes = is_array($classes ?? null) ? $classes : [];
$defaults = [
    'wrapper'        => 'comment-form card card--form',
    'title'          => 'card__title',
    'flash'          => 'alert',
    'form'           => 'form-grid',
    'note'           => 'form-note',
    'field'          => 'form-field',
    'fieldFull'      => 'form-field form-field--full',
    'fieldLabel'     => 'form-field__label',
    'fieldControl'   => 'form-field__control',
    'actions'        => 'form-actions',
    'submit'         => 'btn btn--primary',
    'hint'           => 'form-hint',
    'honeypot'       => 'sr-only',
    'replyInfo'      => 'comment-form__reply-info',
    'replyLabel'     => 'comment-form__reply-label',
    'replyTarget'    => 'comment-form__reply-target',
    'replyCancel'    => 'comment-form__reply-cancel btn btn--link',
];
$classes = $classes + $defaults;

$strings = is_array($strings ?? null) ? $strings : [];
$stringDefaults = [
    'title'            => 'Přidat komentář',
    'signedInAs'       => 'Přihlášen jako %s %s',
    'nameLabel'        => 'Jméno',
    'emailLabel'       => 'E-mail (nepovinné)',
    'commentLabel'     => 'Komentář',
    'submit'           => 'Odeslat',
    'moderationNotice' => 'Komentář bude nejprve schválen.',
    'replyingTo'       => 'Odpovídáte na:',
    'cancelReply'      => 'Zrušit odpověď',
];
$strings = $strings + $stringDefaults;

$esc = static fn(string $value): string => e($value);
$cls = static fn(string $key) => trim((string)($classes[$key] ?? ''));
$h   = $esc;

$isAdmin = ($frontUser['role'] ?? '') === 'admin';
$flashType = $commentFlash['type'] ?? null;
$flashMsg  = $commentFlash['msg'] ?? null;
?>
<section
  class="<?= $esc($cls('wrapper')) ?>"
  data-comment-form
  <?php if ($threadId !== ''): ?>data-comment-thread="<?= $esc($threadId) ?>"<?php endif; ?>
>
  <h3 class="<?= $esc($cls('title')) ?>"><?= $esc((string)$strings['title']) ?></h3>
  <?php if ($flashType && $flashMsg): ?>
    <div class="<?= $esc($cls('flash')) ?> alert--<?= $esc((string)$flashType) ?>"><?= $esc((string)$flashMsg) ?></div>
  <?php endif; ?>

  <div class="<?= $esc($cls('replyInfo')) ?>" data-comment-reply-info hidden>
    <span class="<?= $esc($cls('replyLabel')) ?>"><?= $esc((string)$strings['replyingTo']) ?></span>
    <strong class="<?= $esc($cls('replyTarget')) ?>" data-comment-reply-target></strong>
    <button type="button" class="<?= $esc($cls('replyCancel')) ?>" data-comment-reply-cancel><?= $esc((string)$strings['cancelReply']) ?></button>
  </div>

  <form method="post" action="<?= $esc($action) ?>" class="<?= $esc($cls('form')) ?>" autocomplete="off">
    <?php if ($frontUser): ?>
      <p class="<?= $esc($cls('note')) ?>">
        <?php
        $name = (string)($frontUser['name'] ?? 'Uživatel');
        $email = (string)($frontUser['email'] ?? '');
        $signedText = sprintf((string)$strings['signedInAs'], $name, $email !== '' ? "($email)" : '');
        echo $esc(trim($signedText));
        ?>
      </p>
    <?php else: ?>
      <label class="<?= $esc($cls('field')) ?>">
        <span class="<?= $esc($cls('fieldLabel')) ?>"><?= $esc((string)$strings['nameLabel']) ?></span>
        <input class="<?= $esc($cls('fieldControl')) ?>" name="author_name" required>
      </label>
      <label class="<?= $esc($cls('field')) ?>">
        <span class="<?= $esc($cls('fieldLabel')) ?>"><?= $esc((string)$strings['emailLabel']) ?></span>
        <input class="<?= $esc($cls('fieldControl')) ?>" type="email" name="author_email">
      </label>
    <?php endif; ?>

    <label class="<?= $esc($cls('fieldFull')) ?>">
      <span class="<?= $esc($cls('fieldLabel')) ?>"><?= $esc((string)$strings['commentLabel']) ?></span>
      <textarea class="<?= $esc($cls('fieldControl')) ?>" name="content" rows="5" required></textarea>
    </label>

    <div class="<?= $esc($cls('honeypot')) ?>" aria-hidden="true">
      <label>Website</label>
      <input type="text" name="website" tabindex="-1" autocomplete="off">
    </div>

    <input type="hidden" name="post_id" value="<?= $postId ?>">
    <input type="hidden" name="parent_id" value="0" data-comment-parent>
    <input type="hidden" name="csrf" value="<?= $esc($csrfPublic) ?>">

    <div class="<?= $esc($cls('actions')) ?>">
      <button class="<?= $esc($cls('submit')) ?>" type="submit"><?= $esc((string)$strings['submit']) ?></button>
      <?php if (!$isAdmin): ?>
        <span class="<?= $esc($cls('hint')) ?>"><?= $esc((string)$strings['moderationNotice']) ?></span>
      <?php endif; ?>
    </div>
  </form>
</section>
<script>
(function () {
  if (window.cmsCommentReplyInit) { return; }
  window.cmsCommentReplyInit = true;

  function escapeSelector(value) {
    if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
      return CSS.escape(value);
    }
    return value.replace(/[^a-zA-Z0-9_\-]/g, '\\$&');
  }

  function findForm(trigger) {
    const threadId = trigger.getAttribute('data-comment-thread');
    if (threadId) {
      const scoped = document.querySelector('[data-comment-form][data-comment-thread="' + escapeSelector(threadId) + '"]');
      if (scoped) { return scoped; }
    }
    const list = trigger.closest('[data-comment-list]');
    if (list) {
      const listThread = list.getAttribute('data-comment-thread');
      if (listThread) {
        const scoped = document.querySelector('[data-comment-form][data-comment-thread="' + escapeSelector(listThread) + '"]');
        if (scoped) { return scoped; }
      }
    }
    return document.querySelector('[data-comment-form]');
  }

  document.addEventListener('click', function (event) {
    const trigger = event.target instanceof Element ? event.target.closest('[data-comment-reply-trigger]') : null;
    if (!trigger) { return; }
    const form = findForm(trigger);
    if (!form) { return; }

    const parentInput = form.querySelector('[data-comment-parent]');
    const info = form.querySelector('[data-comment-reply-info]');
    const target = form.querySelector('[data-comment-reply-target]');

    if (parentInput) {
      parentInput.value = trigger.getAttribute('data-comment-id') || '0';
    }
    if (target) {
      target.textContent = trigger.getAttribute('data-comment-author') || '';
    }
    if (info) {
      info.hidden = false;
    }
    if (form instanceof HTMLElement) {
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    event.preventDefault();
  });

  document.addEventListener('click', function (event) {
    const cancel = event.target instanceof Element ? event.target.closest('[data-comment-reply-cancel]') : null;
    if (!cancel) { return; }

    const form = cancel.closest('[data-comment-form]');
    if (!form) { return; }

    const parentInput = form.querySelector('[data-comment-parent]');
    const info = form.querySelector('[data-comment-reply-info]');
    const target = form.querySelector('[data-comment-reply-target]');

    if (parentInput) {
      parentInput.value = '0';
    }
    if (target) {
      target.textContent = '';
    }
    if (info) {
      info.hidden = true;
    }
    event.preventDefault();
  });
})();
</script>
