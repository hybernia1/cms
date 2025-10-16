<?php
/** @var array<string,mixed> $page */

$h  = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$cs = new \Cms\Settings\CmsSettings();
?>
<article class="card card--article">
  <header class="article__header">
    <h1 class="article__title"><?= $h((string)($page['title'] ?? 'Stránka')) ?></h1>
    <div class="article__meta">Aktualizováno: <?= $h($cs->formatDateTime(new \DateTimeImmutable((string)($page['updated_at'] ?? $page['created_at'] ?? 'now')))) ?></div>
  </header>
  <div class="article__content">
    <?= nl2br($h((string)($page['content'] ?? ''))) ?>
  </div>
</article>
