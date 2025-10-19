<?php
/** @var array<string,mixed> $term */
/** @var array<int,array<string,mixed>> $posts */

$name = (string)($term['name'] ?? 'Kategorie');
$description = trim((string)($term['description'] ?? ''));
$postCardTemplate = __DIR__ . '/partials/post-card.php';
?>
<section class="section section--category">
    <header class="section__header">
        <p class="section__eyebrow">Kategorie</p>
        <h1 class="section__title"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if ($description !== ''): ?>
            <p class="section__lead"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </header>

    <?php if ($posts === []): ?>
        <div class="notice notice--info">
            <p>V této kategorii zatím nic nevydáno. Jakmile obsah přidáme, uvidíte ho na tomto místě.</p>
        </div>
    <?php else: ?>
        <div class="post-grid post-grid--term">
            <?php foreach ($posts as $post): ?>
                <?php
                    $postCardHeading = 2;
                    $postCardShowExcerpt = true;
                    $postCardShowMeta = true;
                    $postCardClass = 'post-card--term';
                    $postCardReadMore = 'Zobrazit článek';
                    include $postCardTemplate;
                ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
