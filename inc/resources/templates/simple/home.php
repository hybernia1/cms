<?php
/** @var array<int,array<string,mixed>> $posts */
?>
<section>
    <h2>Nejnovější články</h2>
    <?php if ($posts === []): ?>
        <div class="notice">
            <p>Úvodní stránka zatím nemá žádné zveřejněné příspěvky.</p>
        </div>
    <?php else: ?>
        <div class="post-list">
            <?php foreach ($posts as $post): ?>
                <article class="post-card">
                    <h3><a href="<?= htmlspecialchars((string)$post['permalink'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string)$post['title'], ENT_QUOTES, 'UTF-8'); ?></a></h3>
                    <?php if (!empty($post['published_at'])): ?>
                        <p class="post-card__meta">
                            <time datetime="<?= htmlspecialchars((string)($post['published_at_iso'] ?? $post['published_at']), ENT_QUOTES, 'UTF-8'); ?>">
                                <?= htmlspecialchars((string)$post['published_at'], ENT_QUOTES, 'UTF-8'); ?>
                            </time>
                            <?php if (!empty($post['author'])): ?>
                                · <?= htmlspecialchars((string)$post['author'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                    <p><?= htmlspecialchars((string)$post['excerpt'], ENT_QUOTES, 'UTF-8'); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/partials/newsletter-form.php'; ?>
