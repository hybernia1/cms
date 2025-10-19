<?php
/** @var array<string,mixed> $post */
?>
<article>
    <header>
        <h1><?= htmlspecialchars((string)$post['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if (!empty($post['published_at']) || !empty($post['author'])): ?>
            <p class="post-meta">
                <?php if (!empty($post['published_at'])): ?>
                    <?= htmlspecialchars((string)$post['published_at'], ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
                <?php if (!empty($post['author'])): ?>
                    · <?= htmlspecialchars((string)$post['author'], ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </header>
    <div class="post-content">
        <?= $post['content']; ?>
    </div>
</article>
