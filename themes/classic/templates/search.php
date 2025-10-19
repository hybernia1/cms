<?php
/** @var string $query */
/** @var array<int,array<string,mixed>> $posts */
/** @var \Cms\Admin\Utils\LinkGenerator $links */

$action = htmlspecialchars($links->search(), ENT_QUOTES, 'UTF-8');
$postCardTemplate = __DIR__ . '/partials/post-card.php';
?>
<section class="section section--search">
    <header class="section__header">
        <p class="section__eyebrow">Hledání</p>
        <h1 class="section__title">Najděte, co vás zajímá</h1>
        <p class="section__lead">Zadejte klíčová slova a projděte si odpovídající články či stránky.</p>
    </header>

    <form class="search-form" method="get" action="<?= $action; ?>">
        <label class="search-form__label" for="search-query">Hledaný výraz</label>
        <div class="search-form__group">
            <input id="search-query" type="search" name="s" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Například: novinky, kontakt, výroční zpráva">
            <button type="submit">Vyhledat</button>
        </div>
    </form>

    <?php if ($query === ''): ?>
        <div class="notice notice--muted">
            <p>Zadejte prosím alespoň jedno slovo, podle kterého máme vyhledávat.</p>
        </div>
    <?php elseif ($posts === []): ?>
        <div class="notice notice--warning">
            <p>Pro dotaz „<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>“ jsme nenašli žádný výsledek. Zkuste použít jiná klíčová slova.</p>
        </div>
    <?php else: ?>
        <h2 class="section__subtitle">Nalezené výsledky</h2>
        <div class="post-grid post-grid--search">
            <?php foreach ($posts as $post): ?>
                <?php
                    $postCardHeading = 3;
                    $postCardShowExcerpt = true;
                    $postCardShowMeta = true;
                    $postCardClass = 'post-card--search';
                    $postCardReadMore = 'Zobrazit detail';
                    include $postCardTemplate;
                ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
