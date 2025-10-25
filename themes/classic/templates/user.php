<?php
/** @var array<string,mixed> $user */
/** @var array<int,array<string,mixed>> $posts */
/** @var array<int,array<string,mixed>> $comments */

$user = is_array($user ?? null) ? $user : [];
$posts = is_array($posts ?? null) ? $posts : [];
$comments = is_array($comments ?? null) ? $comments : [];

$name = trim((string)($user['name'] ?? ''));
$postCount = isset($user['post_count']) ? (int)$user['post_count'] : count($posts);
$commentCount = isset($user['comment_count']) ? (int)$user['comment_count'] : count($comments);
$createdAt = isset($user['created_at']) ? (string)$user['created_at'] : '';
$joinedDisplay = '';
if ($createdAt !== '') {
    try {
        $joinedDisplay = (new \DateTimeImmutable($createdAt))->format('j. n. Y H:i');
    } catch (\Exception $exception) {
        $joinedDisplay = '';
    }
}

$websiteUrl = isset($user['website_url']) ? trim((string)$user['website_url']) : '';
$websiteLabel = isset($user['website_label']) ? trim((string)$user['website_label']) : '';
$avatarUrl = isset($user['avatar_url']) ? trim((string)$user['avatar_url']) : '';
$avatarInitial = isset($user['avatar_initial']) ? trim((string)$user['avatar_initial']) : '';
$bio = isset($user['bio']) ? trim((string)$user['bio']) : '';
$bioHtml = '';
if ($bio !== '') {
    $escapedBio = htmlspecialchars($bio, ENT_QUOTES, 'UTF-8');
    $bioHtml = nl2br($escapedBio, false);
}
if ($avatarInitial === '') {
    $first = $name !== '' ? $name : '•';
    if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
        $avatarInitial = mb_strtoupper(mb_substr($first, 0, 1, 'UTF-8'), 'UTF-8');
    } else {
        $avatarInitial = strtoupper(substr($first, 0, 1));
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
        <div class="author-hero">
            <div class="author-hero__avatar" aria-hidden="true">
                <?php if ($avatarUrl !== ''): ?>
                    <img
                        class="author-hero__avatar-image"
                        src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8'); ?>"
                        alt="<?= htmlspecialchars($name !== '' ? $name : 'Autor', ENT_QUOTES, 'UTF-8'); ?>"
                        width="96"
                        height="96"
                    >
                <?php else: ?>
                    <span class="author-hero__avatar-fallback">
                        <?= htmlspecialchars($avatarInitial !== '' ? $avatarInitial : '?', ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="author-hero__content">
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
                <ul class="author-hero__stats" aria-label="Statistiky autora">
                    <li class="author-hero__stat">
                        <span class="author-hero__stat-label">Příspěvky</span>
                        <span class="author-hero__stat-value"><?= htmlspecialchars((string)$postCount, ENT_QUOTES, 'UTF-8'); ?></span>
                    </li>
                    <li class="author-hero__stat">
                        <span class="author-hero__stat-label">Komentáře</span>
                        <span class="author-hero__stat-value"><?= htmlspecialchars((string)$commentCount, ENT_QUOTES, 'UTF-8'); ?></span>
                    </li>
                </ul>
                <?php if ($websiteUrl !== ''): ?>
                    <p class="author-hero__link">
                        <a href="<?= htmlspecialchars($websiteUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                            <?= htmlspecialchars($websiteLabel !== '' ? $websiteLabel : $websiteUrl, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </p>
                <?php endif; ?>
                <?php if ($bioHtml !== ''): ?>
                    <p class="author-hero__bio">
                        <?= $bioHtml; ?>
                    </p>
                <?php endif; ?>
                <?php if ($joinedDisplay !== ''): ?>
                    <p class="section__meta">Registrován <?= htmlspecialchars($joinedDisplay, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
            </div>
        </div>
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

    <div class="author-comments">
        <h2 class="author-comments__title">Komentáře autora</h2>
        <?php if ($comments === []): ?>
            <p class="author-comments__empty">Autor zatím nenapsal žádný komentář.</p>
        <?php else: ?>
            <ul class="author-comments__list">
                <?php foreach ($comments as $comment): ?>
                    <?php
                        $commentContent = (string)($comment['content'] ?? '');
                        $commentSnippet = preg_replace('~\s+~u', ' ', $commentContent);
                        $commentSnippet = trim(is_string($commentSnippet) ? $commentSnippet : '');
                        if ($commentSnippet !== '') {
                            if (function_exists('mb_strlen')) {
                                if (mb_strlen($commentSnippet, 'UTF-8') > 220) {
                                    $commentSnippet = mb_substr($commentSnippet, 0, 217, 'UTF-8') . '…';
                                }
                            } elseif (strlen($commentSnippet) > 220) {
                                $commentSnippet = substr($commentSnippet, 0, 217) . '…';
                            }
                        }
                        $commentDate = trim((string)($comment['created_at'] ?? ''));
                        $commentIso = trim((string)($comment['created_at_iso'] ?? ''));
                        $commentPostTitle = trim((string)($comment['post_title'] ?? ''));
                        $commentPostUrl = trim((string)($comment['post_url'] ?? ''));
                    ?>
                    <li class="author-comments__item">
                        <article class="author-comment">
                            <?php if ($commentPostTitle !== ''): ?>
                                <h3 class="author-comment__title">
                                    <?php if ($commentPostUrl !== ''): ?>
                                        <a href="<?= htmlspecialchars($commentPostUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                            <?= htmlspecialchars($commentPostTitle, ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($commentPostTitle, ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </h3>
                            <?php endif; ?>
                            <?php if ($commentDate !== ''): ?>
                                <time class="author-comment__time" datetime="<?= htmlspecialchars($commentIso !== '' ? $commentIso : $commentDate, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?= htmlspecialchars($commentDate, ENT_QUOTES, 'UTF-8'); ?>
                                </time>
                            <?php endif; ?>
                            <?php if ($commentSnippet !== ''): ?>
                                <p class="author-comment__excerpt">
                                    <?= htmlspecialchars($commentSnippet, ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                            <?php endif; ?>
                        </article>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
