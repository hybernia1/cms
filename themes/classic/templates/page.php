<?php
/** @var array<string,mixed> $page */

$cs = new \Cms\Settings\CmsSettings();
?>
<article class="card card--article">
  <header class="article__header">
    <h1 class="article__title"><?= e((string)($page['title'] ?? 'Stránka')) ?></h1>
    <div class="article__meta">Aktualizováno: <?= e($cs->formatDateTime(new \DateTimeImmutable((string)($page['updated_at'] ?? $page['created_at'] ?? 'now')))) ?></div>
  </header>
  <div class="article__content">
    <?= nl2br(e((string)($page['content'] ?? ''))) ?>
  </div>
</article>
