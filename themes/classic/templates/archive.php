<?php
/** @var array<int,array<string,mixed>> $items */
/** @var string $type */
/** @var array<string,mixed>|null $term */
/** @var \Cms\Utils\LinkGenerator $urls */

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$title = $type !== '' ? $type : 'Archiv';
?>
<section class="card card--section">
  <header class="card__header">
    <h1 class="card__title"><?= $h($title) ?></h1>
    <?php if ($term): ?>
      <span class="card__chip">Slovník: <?= $h((string)($term['slug'] ?? '')) ?></span>
    <?php endif; ?>
  </header>
  <?php $this->part('parts/post-list', [
    'posts'        => $items,
    'emptyMessage' => 'V tomto archivu zatím nic není.',
    'urls'         => $urls,
  ]); ?>
</section>
