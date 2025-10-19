<?php
/** @var array<string,mixed> $term */
/** @var array<int,array<string,mixed>> $posts */
?>
<section>
    <header>
        <h1>Kategorie: <?= htmlspecialchars((string)$term['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if (!empty($term['description'])): ?>
            <p class="term-description"><?= htmlspecialchars((string)$term['description'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </header>
    <?php if ($posts === []): ?>
        <p>V této kategorii zatím nic nevydáno.</p>
    <?php else: ?>
        <div class="post-list">
            <?php foreach ($posts as $post): ?>
                <article class="post-card">
                    <h3><a href="<?= htmlspecialchars((string)$post['permalink'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string)$post['title'], ENT_QUOTES, 'UTF-8'); ?></a></h3>
                    <?php if (!empty($post['published_at'])): ?>
                        <p class="post-meta"><?= htmlspecialchars((string)$post['published_at'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                    <p><?= htmlspecialchars((string)$post['excerpt'], ENT_QUOTES, 'UTF-8'); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
