<?php
/** @var array<string,mixed> $user */
/** @var array<int,array<string,mixed>> $posts */

$user = is_array($user ?? null) ? $user : [];
$posts = is_array($posts ?? null) ? $posts : [];
$name = trim((string)($user['name'] ?? ''));
$postCount = isset($user['post_count']) ? (int)$user['post_count'] : count($posts);
$createdAt = isset($user['created_at']) ? (string)$user['created_at'] : '';
$joinedDisplay = '';
if ($createdAt !== '') {
    try {
        $joinedDisplay = (new \DateTimeImmutable($createdAt))->format('j. n. Y H:i');
    } catch (\Exception $exception) {
        $joinedDisplay = '';
    }
}

$postCardTemplate = __DIR__ . '/partials/post-card.php';
$hasPosts = $posts !== [];
$countLabel = $postCount === 1
    ? '1 zveřejněný příspěvek'
    : $postCount . ' zveřejněných příspěvků';
?>
<section class="section section--author">
    <header class="section__header">
        <p class="section__eyebrow">Autor</p>
        <h1 class="section__title">
            <?= htmlspecialchars($name !== '' ? $name : 'Profil autora', ENT_QUOTES, 'UTF-8'); ?>
        </h1>
        <p class="section__lead">
            <?php if ($hasPosts): ?>
                Najdete zde <?= htmlspecialchars($countLabel, ENT_QUOTES, 'UTF-8'); ?> tohoto autora.
            <?php else: ?>
                Tento autor zatím nemá žádné zveřejněné příspěvky.
            <?php endif; ?>
        </p>
        <?php if ($joinedDisplay !== ''): ?>
            <p class="section__meta">Registrován <?= htmlspecialchars($joinedDisplay, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </header>

    <?php if (!$hasPosts): ?>
        <div class="notice notice--info">
            <p>Zkuste se zastavit později, jakmile autor zveřejní nový obsah.</p>
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
