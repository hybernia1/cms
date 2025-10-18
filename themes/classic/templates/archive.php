<?php
/** @var array<int,array<string,mixed>> $items */
/** @var string $type */
/** @var array<string,mixed>|null $term */
/** @var \Cms\Utils\LinkGenerator $urls */

$title = $type !== '' ? $type : 'Archiv';
?>
<section class="card card--section">
  <header class="card__header">
    <h1 class="card__title"><?= e($title) ?></h1>
    <?php if ($term): ?>
      <span class="card__chip">Slovník: <?= e((string)($term['slug'] ?? '')) ?></span>
    <?php endif; ?>
  </header>
  <?php $this->part('post-list', [
    'posts'        => $items,
    'emptyMessage' => 'V tomto archivu zatím nic není.',
    'urls'         => $urls,
  ]); ?>
</section>
