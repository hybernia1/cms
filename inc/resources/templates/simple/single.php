<?php
/** @var array<string,mixed> $post */
?>
<article>
    <h2><?= htmlspecialchars((string)$post['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
    <?php if (!empty($post['published_at'])): ?>
        <p class="breadcrumbs">Publikováno <?= htmlspecialchars((string)$post['published_at'], ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <div class="post-content">
        <?= $post['content']; ?>
    </div>
</article>
