<?php
/** @var array<string,mixed> $post */
/** @var \Cms\Admin\Utils\LinkGenerator $links */

$title = (string)($post['title'] ?? '');
$author = trim((string)($post['author'] ?? ''));
$published = trim((string)($post['published_at'] ?? ''));
$publishedIso = trim((string)($post['published_at_iso'] ?? ''));
$terms = is_array($post['terms'] ?? null) ? $post['terms'] : [];
$categories = array_values(array_filter($terms, static fn ($term) => ($term['type'] ?? '') === 'category'));
$tags = array_values(array_filter($terms, static fn ($term) => ($term['type'] ?? '') === 'tag'));
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
