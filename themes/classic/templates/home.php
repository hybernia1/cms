<?php
/** @var array<int,array<string,mixed>> $posts */
/** @var \Cms\Admin\Utils\LinkGenerator $links */
?>
<section>
    <header>
        <h1 class="section-title">Čerstvé čtení</h1>
        <p class="breadcrumbs">Nejnovější články a novinky z redakce.</p>
    </header>
    <?php if ($posts === []): ?>
        <p>Obsah pro úvodní stránku se teprve připravuje. Zkuste se vrátit později.</p>
    <?php else: ?>
        <div class="post-list">
            <?php foreach ($posts as $post): ?>
                <article class="post-card">
                    <h3><a href="<?= htmlspecialchars((string)$post['permalink'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string)$post['title'], ENT_QUOTES, 'UTF-8'); ?></a></h3>
                    <?php if (!empty($post['published_at'])): ?>
                        <p class="post-meta"><?= htmlspecialchars((string)$post['published_at'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                    <p><?= htmlspecialchars((string)$post['excerpt'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><a href="<?= htmlspecialchars((string)$post['permalink'], ENT_QUOTES, 'UTF-8'); ?>">Pokračovat ve čtení &rarr;</a></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
