<?php
/** @var string $query */
/** @var array<int,array<string,mixed>> $posts */
/** @var \Cms\Admin\Utils\LinkGenerator $links */
?>
<section>
    <h2>Vyhledávání</h2>
    <form method="get" action="<?= htmlspecialchars($links->search(), ENT_QUOTES, 'UTF-8'); ?>">
        <label>
            <span class="sr-only">Hledat</span>
            <input type="search" name="s" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Zadejte hledaný výraz" style="padding:0.5rem 0.75rem; width:100%; max-width:320px;">
        </label>
        <button type="submit" style="padding:0.5rem 1rem; margin-left:0.5rem;">Hledat</button>
    </form>

    <?php if ($query === ''): ?>
        <p>Zadejte hledaný výraz a potvrďte.</p>
    <?php elseif ($posts === []): ?>
        <p>Pro dotaz „<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>“ jsme nic nenašli.</p>
    <?php else: ?>
        <h3>Výsledky</h3>
        <div class="post-list">
            <?php foreach ($posts as $post): ?>
                <article class="post-card">
                    <h4><a href="<?= htmlspecialchars((string)$post['permalink'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string)$post['title'], ENT_QUOTES, 'UTF-8'); ?></a></h4>
                    <p><?= htmlspecialchars((string)$post['excerpt'], ENT_QUOTES, 'UTF-8'); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
