<?php
/** @var array<string,mixed> $page */
?>
<article class="post-card">
    <header>
        <h1><?= htmlspecialchars((string)$page['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if (!empty($page['published_at']) || !empty($page['author'])): ?>
            <p class="post-card__meta">
                <?php if (!empty($page['published_at'])): ?>
                    <time datetime="<?= htmlspecialchars((string)($page['published_at_iso'] ?? $page['published_at']), ENT_QUOTES, 'UTF-8'); ?>">
                        <?= htmlspecialchars((string)$page['published_at'], ENT_QUOTES, 'UTF-8'); ?>
                    </time>
                <?php endif; ?>
                <?php if (!empty($page['author'])): ?>
                    · <?= htmlspecialchars((string)$page['author'], ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </header>
    <div>
        <?= $page['content']; ?>
    </div>
</article>
