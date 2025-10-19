<?php
/** @var array<string,mixed> $commentNode */
/** @var callable $renderComment */

$children = is_array($commentNode['children'] ?? null) ? $commentNode['children'] : [];
$author = trim((string)($commentNode['author'] ?? 'Anonym'));
$created = trim((string)($commentNode['created_at'] ?? ''));
$createdIso = trim((string)($commentNode['created_at_iso'] ?? ''));
$content = (string)($commentNode['content'] ?? '');
$commentId = isset($commentNode['id']) ? (int)$commentNode['id'] : 0;
$replyEnabled = !empty($commentsAllowed ?? false) && $commentId > 0;
?>
<li class="comment" id="comment-<?= $commentId > 0 ? $commentId : 'x'; ?>">
    <article class="comment__body">
        <header class="comment__header">
            <span class="comment__author"><?= htmlspecialchars($author !== '' ? $author : 'Anonym', ENT_QUOTES, 'UTF-8'); ?></span>
            <?php if ($created !== ''): ?>
                <time class="comment__time" datetime="<?= htmlspecialchars($createdIso !== '' ? $createdIso : $created, ENT_QUOTES, 'UTF-8'); ?>">
                    <?= htmlspecialchars($created, ENT_QUOTES, 'UTF-8'); ?>
                </time>
            <?php endif; ?>
        </header>
        <div class="comment__content">
            <?= nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8')); ?>
        </div>
        <?php if ($replyEnabled): ?>
            <footer class="comment__footer">
                <button
                    class="comment__reply"
                    type="button"
                    data-comment-reply
                    data-comment-id="<?= $commentId; ?>"
                >
                    Odpovědět
                </button>
            </footer>
        <?php endif; ?>
    </article>
    <?php if ($children !== []): ?>
        <ol class="comment__children">
            <?php foreach ($children as $childNode): ?>
                <?php $renderComment($childNode); ?>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</li>
