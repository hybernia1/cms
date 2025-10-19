<?php
/** @var array<string,mixed> $page */
?>
<article>
    <h2><?= htmlspecialchars((string)$page['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
    <div class="post-content">
        <?= $page['content']; ?>
    </div>
</article>
