<?php
/** @var array<int,array<string,mixed>> $posts */
/** @var string $type */
?>
<section>
    <h2>Archiv typu <?= htmlspecialchars(ucfirst($type), ENT_QUOTES, 'UTF-8'); ?></h2>
    <?php if ($posts === []): ?>
        <p>Pro tento typ zatím nemáme obsah.</p>
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
