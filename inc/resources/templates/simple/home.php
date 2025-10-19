<?php
/** @var array<int,array<string,mixed>> $posts */
/** @var \Cms\Admin\Utils\LinkGenerator $links */
?>
<section>
    <h2>Nejnovější články</h2>
    <?php if ($posts === []): ?>
        <p>Obsah se připravuje.</p>
    <?php else: ?>
        <div class="post-list">
            <?php foreach ($posts as $post): ?>
                <article class="post-card">
                    <h3><a href="<?= htmlspecialchars((string)$post['permalink'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string)$post['title'], ENT_QUOTES, 'UTF-8'); ?></a></h3>
                    <p><?= htmlspecialchars((string)$post['excerpt'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><a href="<?= htmlspecialchars((string)$post['permalink'], ENT_QUOTES, 'UTF-8'); ?>">Pokračovat ve čtení &rarr;</a></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
