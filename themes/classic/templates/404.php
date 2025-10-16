<?php
/** @var \Cms\Utils\LinkGenerator $urls */

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<section class="card card--centered">
  <h1 class="card__title">Stránka nenalezena</h1>
  <p class="muted">Obsah, který hledáte, byl pravděpodobně přesunut nebo už neexistuje.</p>
  <a class="btn btn--primary" href="<?= $h($urls->home()) ?>">Zpět na úvod</a>
</section>
