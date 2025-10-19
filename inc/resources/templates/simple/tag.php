<?php
/** @var array<string,mixed> $term */
/** @var array<int,array<string,mixed>> $posts */
?>
<section>
    <h1>Štítek: <?= htmlspecialchars((string)($term['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
    <?php if (!empty($term['description'])): ?>
        <p><?= htmlspecialchars((string)$term['description'], ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <?php if ($posts === []): ?>
        <div class="notice"><p>Pod tímto štítkem zatím nic nemáme.</p></div>
    <?php else: ?>
        <div class="post-list">
            <?php foreach ($posts as $post): ?>
                <article class="post-card">
                    <h2><a href="<?= htmlspecialchars((string)$post['permalink'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string)$post['title'], ENT_QUOTES, 'UTF-8'); ?></a></h2>
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
