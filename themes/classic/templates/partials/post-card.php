<?php
/** @var array<string,mixed> $post */
/** @var int|null $postCardHeading */
/** @var bool|null $postCardShowExcerpt */
/** @var bool|null $postCardShowMeta */
/** @var string|null $postCardClass */
/** @var string|null $postCardReadMore */

$post = is_array($post ?? null) ? $post : [];
$headingLevel = isset($postCardHeading) ? max(1, (int)$postCardHeading) : 2;
$showExcerpt = isset($postCardShowExcerpt) ? (bool)$postCardShowExcerpt : true;
$showMeta = isset($postCardShowMeta) ? (bool)$postCardShowMeta : true;
$cardClass = trim((string)($postCardClass ?? ''));
$readMore = trim((string)($postCardReadMore ?? 'Celý článek'));

$title = (string)($post['title'] ?? '');
$permalink = (string)($post['permalink'] ?? '');
$excerpt = (string)($post['excerpt'] ?? '');
$author = trim((string)($post['author'] ?? ''));
$published = trim((string)($post['published_at'] ?? ''));
$publishedIso = trim((string)($post['published_at_iso'] ?? ''));

$headingTag = 'h' . $headingLevel;
?>
<article class="post-card<?= $cardClass !== '' ? ' ' . htmlspecialchars($cardClass, ENT_QUOTES, 'UTF-8') : ''; ?>">
    <<?= $headingTag; ?> class="post-card__title">
        <a class="post-card__link" href="<?= htmlspecialchars($permalink, ENT_QUOTES, 'UTF-8'); ?>">
            <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>
        </a>
    </<?= $headingTag; ?>>

    <?php if ($showMeta && ($published !== '' || $author !== '')): ?>
        <p class="post-card__meta">
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

    <?php if ($showExcerpt && $excerpt !== ''): ?>
        <p class="post-card__excerpt">
            <?= htmlspecialchars($excerpt, ENT_QUOTES, 'UTF-8'); ?>
        </p>
    <?php endif; ?>

    <p class="post-card__cta">
        <a class="post-card__read-more" href="<?= htmlspecialchars($permalink, ENT_QUOTES, 'UTF-8'); ?>">
            <?= htmlspecialchars($readMore, ENT_QUOTES, 'UTF-8'); ?>
        </a>
    </p>
</article>
<?php
unset($postCardHeading, $postCardShowExcerpt, $postCardShowMeta, $postCardClass, $postCardReadMore);
