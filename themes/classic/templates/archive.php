<?php
/** @var array<int,array<string,mixed>> $posts */
/** @var string $type */

$type = $type !== '' ? $type : 'post';
$title = ucfirst($type);
$postCardTemplate = __DIR__ . '/partials/post-card.php';
?>
<section class="section section--archive">
    <header class="section__header">
        <p class="section__eyebrow">Archiv</p>
        <h1 class="section__title">Všechny položky typu <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="section__lead">
            Nahlížíte do kompletního archivu obsahu typu <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>.
        </p>
    </header>

    <?php if ($posts === []): ?>
        <div class="notice notice--info">
            <p>V tomto archivu zatím nic není. Jakmile něco zveřejníme, přibude to právě sem.</p>
        </div>
    <?php else: ?>
        <div class="post-grid post-grid--archive">
            <?php foreach ($posts as $post): ?>
                <?php
                    $postCardHeading = 2;
                    $postCardShowExcerpt = true;
                    $postCardShowMeta = true;
                    $postCardClass = 'post-card--archive';
                    $postCardReadMore = 'Otevřít detail';
                    include $postCardTemplate;
                ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
