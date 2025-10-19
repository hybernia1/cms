<?php
/** @var array<string,mixed> $term */
/** @var array<int,array<string,mixed>> $posts */

$name = (string)($term['name'] ?? 'Štítek');
$description = trim((string)($term['description'] ?? ''));
$postCardTemplate = __DIR__ . '/partials/post-card.php';
?>
<section class="section section--tag">
    <header class="section__header">
        <p class="section__eyebrow">Štítek</p>
        <h1 class="section__title"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if ($description !== ''): ?>
            <p class="section__lead"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </header>

    <?php if ($posts === []): ?>
        <div class="notice notice--info">
            <p>Pod tímto štítkem zatím nic nemáme. Sledujte nás, nové články přibývají průběžně.</p>
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
