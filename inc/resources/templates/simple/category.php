<?php
/** @var array<string,mixed> $term */
/** @var array<int,array<string,mixed>> $posts */
?>
<section>
    <h2>Kategorie: <?= htmlspecialchars((string)$term['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
    <?php if (!empty($term['description'])): ?>
        <p><?= htmlspecialchars((string)$term['description'], ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <?php if ($posts === []): ?>
        <p>Zatím žádné články v této kategorii.</p>
    <?php else: ?>
        <div class="post-list">
            <?php foreach ($posts as $post): ?>
                <article class="post-card">
                    <h3><a href="<?= htmlspecialchars((string)$post['permalink'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string)$post['title'], ENT_QUOTES, 'UTF-8'); ?></a></h3>
                    <p><?= htmlspecialchars((string)$post['excerpt'], ENT_QUOTES, 'UTF-8'); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
