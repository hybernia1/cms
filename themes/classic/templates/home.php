<?php
/** @var array<int,array<string,mixed>> $posts */

$siteTitle = theme_site('title');
$siteTagline = theme_site('description');
$lead = $siteTagline !== ''
    ? $siteTagline
    : 'Prohlédněte si nejnovější články, aktuality a příběhy, které na ' . $siteTitle . ' právě vznikají.';
$postCardTemplate = __DIR__ . '/partials/post-card.php';
?>
<section class="section section--home">
    <header class="section__header">
        <p class="section__eyebrow">Aktuálně</p>
        <h1 class="section__title">Čerstvé čtení</h1>
        <p class="section__lead">
            <?= htmlspecialchars($lead, ENT_QUOTES, 'UTF-8'); ?>
        </p>
    </header>

    <?php if ($posts === []): ?>
        <div class="notice notice--info">
            <p>Úvodní stránka zatím nemá žádné zveřejněné články. Jakmile redakce něco připraví, objeví se to právě zde.</p>
        </div>
    <?php else: ?>
        <div class="post-grid">
            <?php foreach ($posts as $post): ?>
                <?php
                    $postCardHeading = 2;
                    $postCardShowExcerpt = true;
                    $postCardShowMeta = true;
                    $postCardClass = 'post-card--featured';
                    $postCardReadMore = 'Pokračovat ve čtení';
                    include $postCardTemplate;
                ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
