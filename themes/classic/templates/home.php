<?php
/** @var array<int,array<string,mixed>> $latestPosts */
/** @var \Cms\Utils\LinkGenerator $urls */
?>
<section class="card card--section">
  <header class="card__header">
    <h1 class="card__title">Poslední příspěvky</h1>
    <a class="btn btn--ghost" href="<?= e($urls->type('post')) ?>">Archiv</a>
  </header>
  <?php $this->part('post-list', [
    'posts'        => $latestPosts,
    'emptyMessage' => 'Zatím žádné příspěvky.',
    'urls'         => $urls,
  ]); ?>
</section>
