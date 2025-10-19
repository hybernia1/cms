<?php
/** @var array<string,mixed> $post */

$categories = array_values(array_filter(is_array($post['terms'] ?? null) ? $post['terms'] : [], static fn ($term) => ($term['type'] ?? '') === 'category'));
$tags = array_values(array_filter(is_array($post['terms'] ?? null) ? $post['terms'] : [], static fn ($term) => ($term['type'] ?? '') === 'tag'));
?>
<article class="post-card">
    <header>
        <h1><?= htmlspecialchars((string)$post['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if (!empty($post['published_at']) || !empty($post['author'])): ?>
            <p class="post-card__meta">
                <?php if (!empty($post['published_at'])): ?>
                    <time datetime="<?= htmlspecialchars((string)($post['published_at_iso'] ?? $post['published_at']), ENT_QUOTES, 'UTF-8'); ?>">
                        <?= htmlspecialchars((string)$post['published_at'], ENT_QUOTES, 'UTF-8'); ?>
                    </time>
                <?php endif; ?>
                <?php if (!empty($post['author'])): ?>
                    · <?= htmlspecialchars((string)$post['author'], ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <?php if ($categories !== []): ?>
            <p><strong>Kategorie:</strong> <?= htmlspecialchars(implode(', ', array_map(static fn ($term) => (string)($term['name'] ?? ''), $categories)), ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </header>
    <div>
        <?= $post['content']; ?>
    </div>
    <?php if ($tags !== []): ?>
        <footer>
            <p><strong>Štítky:</strong> <?= htmlspecialchars(implode(', ', array_map(static fn ($term) => (string)($term['name'] ?? ''), $tags)), ENT_QUOTES, 'UTF-8'); ?></p>
        </footer>
    <?php endif; ?>
</article>
