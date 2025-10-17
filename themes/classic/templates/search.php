<?php
/** @var array<int,array<string,mixed>> $items */
/** @var string $query */
/** @var \Cms\Utils\LinkGenerator $urls */
?>
<section class="card card--section">
  <header class="card__header card__header--stacked">
    <h1 class="card__title">Hledání</h1>
    <?php $this->part('search', 'form', [
      'query'   => $query,
      'action'  => $urls->search(),
      'classes' => [
        'input' => 'form-field__control',
      ],
    ]); ?>
  </header>
  <?php $this->part('post-list', [
    'posts'        => $items,
    'emptyMessage' => $query === '' ? 'Zadejte hledaný výraz.' : 'Nic nebylo nalezeno.',
    'urls'         => $urls,
    'showType'     => true,
  ]); ?>
</section>
