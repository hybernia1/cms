<?php
/** @var array<int,array<string,mixed>>|\Traversable|null $items */
/** @var string $query */
/** @var \Cms\Utils\LinkGenerator $urls */

$hasQuery = trim($query) !== '';

if (is_array($items)) {
  $posts = array_values($items);
} elseif ($items instanceof \Traversable) {
  $posts = iterator_to_array($items, false);
} else {
  $posts = [];
}

$heading = $hasQuery ? sprintf('Hledání: %s', $query) : 'Hledání';
$emptyMessage = $hasQuery ? 'Nic nebylo nalezeno.' : 'Zadejte hledaný výraz.';
$resultCount = count($posts);
if ($resultCount === 1) {
  $resultLabel = 'Nalezen 1 výsledek.';
} elseif ($resultCount >= 2 && $resultCount <= 4) {
  $resultLabel = sprintf('Nalezeny %d výsledky.', $resultCount);
} else {
  $resultLabel = sprintf('Nalezeno %d výsledků.', $resultCount);
}
?>
<section class="card card--section">
  <header class="card__header card__header--stacked">
    <h1 class="card__title"><?= e($heading) ?></h1>
    <?php $this->part('search', 'form', [
      'query'   => $query,
      'action'  => $urls->search(),
      'classes' => [
        'input' => 'form-field__control',
      ],
    ]); ?>
    <?php if ($hasQuery && $resultCount > 0): ?>
      <p class="muted"><?= e($resultLabel) ?></p>
    <?php endif; ?>
  </header>
  <?php $this->part('post-list', [
    'posts'        => $posts,
    'emptyMessage' => $emptyMessage,
    'urls'         => $urls,
    'showType'     => true,
  ]); ?>
</section>
