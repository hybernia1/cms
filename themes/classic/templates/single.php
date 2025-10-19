<?php
/** @var array<string,mixed> $post */
/** @var \Cms\Admin\Utils\LinkGenerator $links */
/** @var array<int,array<string,mixed>> $comments */
/** @var array<string,mixed> $commentForm */
/** @var bool $commentsAllowed */
/** @var int $commentCount */

$title = (string)($post['title'] ?? '');
$author = trim((string)($post['author'] ?? ''));
$published = trim((string)($post['published_at'] ?? ''));
$publishedIso = trim((string)($post['published_at_iso'] ?? ''));
$terms = is_array($post['terms'] ?? null) ? $post['terms'] : [];
$categories = array_values(array_filter($terms, static fn ($term) => ($term['type'] ?? '') === 'category'));
$tags = array_values(array_filter($terms, static fn ($term) => ($term['type'] ?? '') === 'tag'));
$comments = is_array($comments ?? null) ? $comments : [];
$commentCount = isset($commentCount) ? (int)$commentCount : count($comments);
$commentsAllowed = isset($commentsAllowed) ? (bool)$commentsAllowed : false;
$commentForm = is_array($commentForm ?? null) ? $commentForm : [];
$commentErrors = is_array($commentForm['errors'] ?? null) ? $commentForm['errors'] : [];
$commentOld = is_array($commentForm['old'] ?? null) ? $commentForm['old'] : [];
$commentMessage = isset($commentForm['message']) ? trim((string)$commentForm['message']) : '';
$commentSuccess = !empty($commentForm['success']);
$commentUser = is_array($commentForm['user'] ?? null) ? $commentForm['user'] : null;
$commentUserId = isset($commentUser['id']) ? (int)$commentUser['id'] : 0;
$commentUserName = trim((string)($commentUser['name'] ?? ''));
$commentUserEmail = trim((string)($commentUser['email'] ?? ''));
$commentUserDisplay = $commentUserName !== ''
    ? $commentUserName
    : ($commentUserEmail !== '' ? $commentUserEmail : 'uživatel');
$commentUserLogged = $commentUser !== null && ($commentUserId > 0 || $commentUserName !== '' || $commentUserEmail !== '');
$commentTemplate = __DIR__ . '/partials/comment.php';
$renderComment = static function (array $commentNode) use (&$renderComment, $commentTemplate, $commentsAllowed): void {
    include $commentTemplate;
};
?>
<article class="entry entry--single">
    <header class="entry__header">
        <p class="entry__eyebrow">Článek</p>
        <h1 class="entry__title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if ($published !== '' || $author !== ''): ?>
            <p class="entry__meta">
                <?php if ($published !== ''): ?>
                    <time datetime="<?= htmlspecialchars($publishedIso ?: $published, ENT_QUOTES, 'UTF-8'); ?>">
                        <?= htmlspecialchars($published, ENT_QUOTES, 'UTF-8'); ?>
                    </time>
                <?php endif; ?>
                <?php if ($published !== '' && $author !== ''): ?>
                    <span aria-hidden="true">·</span>
                <?php endif; ?>
                <?php if ($author !== ''): ?>
                    <span><?= htmlspecialchars($author, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <?php if ($categories !== []): ?>
            <ul class="entry__terms entry__terms--categories">
                <?php foreach ($categories as $category): ?>
                    <?php
                        $categoryName = (string)($category['name'] ?? '');
                        $categorySlug = (string)($category['slug'] ?? '');
                        $categoryUrl = $categorySlug !== '' ? $links->category($categorySlug) : $links->home();
                    ?>
                    <li>
                        <a href="<?= htmlspecialchars($categoryUrl, ENT_QUOTES, 'UTF-8'); ?>" class="chip chip--category">
                            <?= htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </header>

    <div class="entry__content post-content">
        <?= $post['content']; ?>
    </div>

    <?php if ($tags !== []): ?>
        <footer class="entry__footer">
            <h2 class="entry__footer-title">Štítky</h2>
            <ul class="entry__terms entry__terms--tags">
                <?php foreach ($tags as $tag): ?>
                    <?php
                        $tagName = (string)($tag['name'] ?? '');
                        $tagSlug = (string)($tag['slug'] ?? '');
                        $tagUrl = $tagSlug !== '' ? $links->tag($tagSlug) : $links->home();
                    ?>
                    <li>
                        <a href="<?= htmlspecialchars($tagUrl, ENT_QUOTES, 'UTF-8'); ?>" class="chip chip--tag">
                            <?= htmlspecialchars($tagName, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </footer>
    <?php endif; ?>
</article>

<?php
    $oldName = trim((string)($commentOld['name'] ?? ''));
    $oldEmail = trim((string)($commentOld['email'] ?? ''));
    $oldContent = trim((string)($commentOld['content'] ?? ''));
    $oldParent = isset($commentOld['parent_id']) ? (int)$commentOld['parent_id'] : 0;
    if ($commentUserLogged) {
        if ($commentUserName !== '') {
            $oldName = $commentUserName;
        }
        if ($commentUserEmail !== '') {
            $oldEmail = $commentUserEmail;
        }
    }
    $nameErrors = is_array($commentErrors['name'] ?? null) ? $commentErrors['name'] : [];
    $emailErrors = is_array($commentErrors['email'] ?? null) ? $commentErrors['email'] : [];
    $contentErrors = is_array($commentErrors['content'] ?? null) ? $commentErrors['content'] : [];
    $generalErrors = is_array($commentErrors['general'] ?? null) ? $commentErrors['general'] : [];
    $parentErrors = is_array($commentErrors['parent'] ?? null) ? $commentErrors['parent'] : [];
    $noticeType = $commentSuccess ? 'success' : (!empty($commentErrors) ? 'warning' : 'info');
?>
<section class="comments" id="comments">
    <header class="comments__header">
        <h2 class="comments__title">
            Komentáře
            <?php if ($commentCount > 0): ?>
                <span class="comments__count">(<?= (int)$commentCount; ?>)</span>
            <?php endif; ?>
        </h2>
    </header>

    <?php if ($commentCount === 0): ?>
        <p class="comments__empty">Buďte první, kdo napíše komentář.</p>
    <?php else: ?>
        <ol class="comment-list">
            <?php foreach ($comments as $commentItem): ?>
                <?php $renderComment($commentItem); ?>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>

    <?php if ($commentMessage !== ''): ?>
        <div class="notice notice--<?= htmlspecialchars($noticeType, ENT_QUOTES, 'UTF-8'); ?>">
            <p><?= htmlspecialchars($commentMessage, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php if ($generalErrors !== []): ?>
                <p><?= htmlspecialchars((string)$generalErrors[0], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <?php if ($parentErrors !== []): ?>
                <p><?= htmlspecialchars((string)$parentErrors[0], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($commentsAllowed): ?>
        <div class="comment-form" id="respond">
            <h3 class="comment-form__title">Přidat komentář</h3>
            <?php if ($commentUserLogged): ?>
                <p class="comment-form__note">
                    Přihlášeni jako <strong><?= htmlspecialchars($commentUserDisplay, ENT_QUOTES, 'UTF-8'); ?></strong>. Váš komentář se zveřejní okamžitě.
                </p>
            <?php endif; ?>

            <form class="comment-form__body" method="post" action="" id="comment-form">
                <input type="hidden" name="comment_form" value="1">
                <input type="hidden" name="comment_post" value="<?= (int)($post['id'] ?? 0); ?>">
                <input type="hidden" name="comment_parent" value="<?= $oldParent > 0 ? $oldParent : 0; ?>">

                <div class="comment-form__reply-note" data-comment-reply-note<?= $oldParent > 0 ? '' : ' hidden'; ?>>
                    <span>Odpovídáte na <strong data-comment-reply-target>vybraný komentář</strong>.</span>
                    <span class="comment-form__reply-note-info" data-comment-reply-info hidden>Odpověď bude připojena k hlavnímu komentáři.</span>
                    <button type="button" class="comment-form__reply-cancel" data-comment-reply-cancel>Zrušit odpověď</button>
                </div>

                <div class="comment-form__row">
                    <label class="comment-form__label" for="comment-name">Jméno</label>
                    <input
                        class="comment-form__input"
                        type="text"
                        id="comment-name"
                        name="comment_name"
                        value="<?= htmlspecialchars($oldName, ENT_QUOTES, 'UTF-8'); ?>"
                        required<?= $commentUserLogged ? ' readonly' : ''; ?>
                    >
                    <?php if ($nameErrors !== []): ?>
                        <p class="comment-form__error"><?= htmlspecialchars((string)$nameErrors[0], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                </div>

                <div class="comment-form__row">
                    <label class="comment-form__label" for="comment-email">E-mail</label>
                    <input
                        class="comment-form__input"
                        type="email"
                        id="comment-email"
                        name="comment_email"
                        value="<?= htmlspecialchars($oldEmail, ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="např. jana@example.cz"<?= $commentUserLogged && $commentUserEmail !== '' ? ' readonly' : ''; ?>
                    >
                    <?php if ($emailErrors !== []): ?>
                        <p class="comment-form__error"><?= htmlspecialchars((string)$emailErrors[0], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                </div>

                <div class="comment-form__row">
                    <label class="comment-form__label" for="comment-content">Komentář</label>
                    <textarea
                        class="comment-form__textarea"
                        id="comment-content"
                        name="comment_content"
                        rows="6"
                        required
                    ><?= htmlspecialchars($oldContent, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <?php if ($contentErrors !== []): ?>
                        <p class="comment-form__error"><?= htmlspecialchars((string)$contentErrors[0], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                </div>

                <button class="comment-form__submit" type="submit">Odeslat komentář</button>
            </form>
        </div>
    <?php else: ?>
        <?php if ($commentMessage === ''): ?>
            <div class="notice notice--muted">
                <p>Komentáře jsou u tohoto článku uzavřeny.</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php if ($commentsAllowed): ?>
    <script>
        (function () {
            'use strict';
            document.addEventListener('DOMContentLoaded', function () {
                var form = document.getElementById('comment-form');
                if (!form) {
                    return;
                }
                var parentInput = form.querySelector('input[name="comment_parent"]');
                if (!parentInput) {
                    return;
                }
                var note = form.querySelector('[data-comment-reply-note]');
                var noteTarget = note ? note.querySelector('[data-comment-reply-target]') : null;
                var cancelBtn = form.querySelector('[data-comment-reply-cancel]');
                var noteInfo = note ? note.querySelector('[data-comment-reply-info]') : null;
                var textarea = form.querySelector('textarea[name="comment_content"]');
                var activeComment = null;
                var defaultTargetText = noteTarget && noteTarget.textContent ? noteTarget.textContent.trim() : 'vybraný komentář';

                if (noteTarget) {
                    noteTarget.textContent = defaultTargetText;
                }

                var scrollToForm = function () {
                    if (typeof form.scrollIntoView === 'function') {
                        try {
                            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        } catch (e) {
                            form.scrollIntoView();
                        }
                    }
                };

                var resetReply = function () {
                    parentInput.value = '0';
                    if (note) {
                        note.hidden = true;
                    }
                    if (noteTarget) {
                        noteTarget.textContent = defaultTargetText;
                    }
                    if (noteInfo) {
                        noteInfo.hidden = true;
                    }
                    if (activeComment) {
                        activeComment.classList.remove('comment--replying');
                        activeComment = null;
                    }
                };

                var activateReply = function (commentId, targetId) {
                    parentInput.value = targetId;
                    var commentElement = document.getElementById('comment-' + targetId);
                    if (activeComment) {
                        activeComment.classList.remove('comment--replying');
                    }
                    if (commentElement) {
                        activeComment = commentElement;
                        activeComment.classList.add('comment--replying');
                    } else {
                        activeComment = null;
                    }
                    if (note) {
                        if (noteTarget) {
                            var author = commentElement ? commentElement.querySelector('.comment__author') : null;
                            var authorName = author ? author.textContent.trim() : '';
                            noteTarget.textContent = authorName !== '' ? authorName : defaultTargetText;
                        }
                        note.hidden = false;
                        if (noteInfo) {
                            noteInfo.hidden = !(commentId && targetId && commentId !== targetId);
                        }
                    }
                    if (textarea) {
                        textarea.focus();
                    }
                    scrollToForm();
                };

                document.addEventListener('click', function (event) {
                    var trigger = event.target.closest ? event.target.closest('[data-comment-reply]') : null;
                    if (!trigger) {
                        return;
                    }
                    event.preventDefault();
                    var commentId = trigger.getAttribute('data-comment-id');
                    var targetId = trigger.getAttribute('data-comment-target') || commentId;
                    if (!commentId) {
                        return;
                    }
                    activateReply(commentId, targetId);
                });

                if (cancelBtn) {
                    cancelBtn.addEventListener('click', function (event) {
                        event.preventDefault();
                        resetReply();
                        scrollToForm();
                    });
                }

                if (parentInput.value && parentInput.value !== '0') {
                    activateReply(parentInput.value, parentInput.value);
                }
            });
        })();
    </script>
<?php endif; ?>
