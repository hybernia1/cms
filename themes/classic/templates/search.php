<?php
/** @var string $query */
/** @var array<int,array<string,mixed>> $posts */
/** @var \Cms\Admin\Utils\LinkGenerator $links */
?>
<section>
    <header>
        <h1>Vyhledávání</h1>
        <p class="breadcrumbs">Najděte si přesně to, co vás zajímá.</p>
    </header>
    <form class="search-form" method="get" action="<?= htmlspecialchars($links->search(), ENT_QUOTES, 'UTF-8'); ?>">
        <label class="sr-only" for="search-query">Hledat</label>
        <input id="search-query" type="search" name="s" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Zadejte hledaný výraz">
        <button type="submit">Hledat</button>
    </form>

    <?php if ($query === ''): ?>
        <p>Nejdříve prosím zadejte, co máme vyhledat.</p>
    <?php elseif ($posts === []): ?>
        <p>Pro dotaz „<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>“ jsme nic nenašli.</p>
    <?php else: ?>
        <h2>Výsledky</h2>
        <div class="post-list">
            <?php foreach ($posts as $post): ?>
                <article class="post-card">
                    <h4><a href="<?= htmlspecialchars((string)$post['permalink'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string)$post['title'], ENT_QUOTES, 'UTF-8'); ?></a></h4>
                    <?php if (!empty($post['published_at'])): ?>
                        <p class="post-meta"><?= htmlspecialchars((string)$post['published_at'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                    <p><?= htmlspecialchars((string)$post['excerpt'], ENT_QUOTES, 'UTF-8'); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
