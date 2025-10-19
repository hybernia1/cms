<?php
/** @var string $query */
/** @var array<int,array<string,mixed>> $posts */
/** @var \Cms\Admin\Utils\LinkGenerator $links */

$action = htmlspecialchars($links->search(), ENT_QUOTES, 'UTF-8');
?>
<section>
    <h1>Vyhledávání</h1>
    <form method="get" action="<?= $action; ?>">
        <label for="search-query">Hledaný výraz</label>
        <input id="search-query" type="search" name="s" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit">Hledat</button>
    </form>

    <?php if ($query === ''): ?>
        <div class="notice"><p>Zadejte prosím, co máme vyhledat.</p></div>
    <?php elseif ($posts === []): ?>
        <div class="notice"><p>Nenašli jsme žádný výsledek pro „<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>“.</p></div>
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
