<?php
/** @var array<int,array<string,mixed>> $items */
/** @var string $query */
/** @var \Cms\Utils\LinkGenerator $urls */

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<section class="card card--section">
  <header class="card__header card__header--stacked">
    <h1 class="card__title">Hledání</h1>
    <form class="search-form" method="get" action="<?= $h($urls->search()) ?>">
      <input type="text" class="form-field__control" name="s" value="<?= $h($query) ?>" placeholder="Co hledáte?">
      <button class="btn btn--primary">Hledat</button>
    </form>
  </header>
  <?php $this->part('parts/post-list', [
    'posts'        => $items,
    'emptyMessage' => $query === '' ? 'Zadejte hledaný výraz.' : 'Nic nebylo nalezeno.',
    'urls'         => $urls,
    'showType'     => true,
  ]); ?>
</section>
