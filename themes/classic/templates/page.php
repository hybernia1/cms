<?php
/** @var array<string,mixed> $page */

$title = (string)($page['title'] ?? '');
$author = trim((string)($page['author'] ?? ''));
$authorUrl = (string)($page['author_url'] ?? '');
$published = trim((string)($page['published_at'] ?? ''));
$publishedIso = trim((string)($page['published_at_iso'] ?? ''));
$thumbnail = is_array($page['thumbnail'] ?? null) ? $page['thumbnail'] : null;
$thumbnailUrl = (string)($page['thumbnail_url'] ?? ($thumbnail['url'] ?? ''));
$thumbnailMeta = is_array($page['thumbnail_meta'] ?? null)
    ? $page['thumbnail_meta']
    : (is_array($thumbnail['meta'] ?? null) ? $thumbnail['meta'] : []);
$thumbnailWebpUrl = (string)($page['thumbnail_webp_url'] ?? ($thumbnail['webp_url'] ?? ($thumbnailMeta['webp_url'] ?? '')));
$thumbnailWidth = isset($thumbnailMeta['width']) ? (int)$thumbnailMeta['width'] : 0;
$thumbnailHeight = isset($thumbnailMeta['height']) ? (int)$thumbnailMeta['height'] : 0;
?>
<article class="entry entry--page">
    <header class="entry__header">
        <p class="entry__eyebrow">Stránka</p>
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
                    <?php if ($authorUrl !== ''): ?>
                        <a href="<?= htmlspecialchars($authorUrl, ENT_QUOTES, 'UTF-8'); ?>">
                            <?= htmlspecialchars($author, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    <?php else: ?>
                        <span><?= htmlspecialchars($author, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </header>

    <?php if ($thumbnailUrl !== ''): ?>
        <figure class="entry__thumbnail">
            <picture>
                <?php if ($thumbnailWebpUrl !== ''): ?>
                    <source
                        type="image/webp"
                        srcset="<?= htmlspecialchars($thumbnailWebpUrl, ENT_QUOTES, 'UTF-8'); ?>"
                    >
                <?php endif; ?>
                <img
                    src="<?= htmlspecialchars($thumbnailUrl, ENT_QUOTES, 'UTF-8'); ?>"
                    alt="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>"
                    loading="lazy"
                    <?= $thumbnailWidth > 0 ? 'width="' . $thumbnailWidth . '"' : ''; ?>
                    <?= $thumbnailHeight > 0 ? 'height="' . $thumbnailHeight . '"' : ''; ?>
                >
            </picture>
        </figure>
    <?php endif; ?>

    <div class="entry__content post-content">
        <?= $page['content']; ?>
    </div>
</article>
