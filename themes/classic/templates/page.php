<?php
/** @var array<string,mixed> $page */

$title = (string)($page['title'] ?? '');
$author = trim((string)($page['author'] ?? ''));
$published = trim((string)($page['published_at'] ?? ''));
$publishedIso = trim((string)($page['published_at_iso'] ?? ''));
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
                    <span><?= htmlspecialchars($author, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </header>

    <div class="entry__content post-content">
        <?= $page['content']; ?>
    </div>
</article>
