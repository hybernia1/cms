<?php
/** @var array<string,mixed> $page */
?>
<article>
    <header>
        <h1><?= htmlspecialchars((string)$page['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
    </header>
    <div class="post-content">
        <?= $page['content']; ?>
    </div>
</article>
