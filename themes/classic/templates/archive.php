<?php
/** @var array<int,array<string,mixed>> $posts */
/** @var string $type */
?>
<section>
    <header>
        <h1>Archiv: <?= htmlspecialchars(ucfirst($type), ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="breadcrumbs">Prohlížíte si všechny položky tohoto typu obsahu.</p>
    </header>
    <?php if ($posts === []): ?>
        <p>Ještě jsme v tomto archivu nic nezveřejnili.</p>
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
