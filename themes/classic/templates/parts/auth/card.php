<?php
/** @var string $title */
/** @var string|null $type */
/** @var string|null $msg */
/** @var string $body */

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<section class="card card--auth">
  <h1 class="card__title"><?= $h($title) ?></h1>
  <?php if (!empty($type) && !empty($msg)): ?>
    <div class="alert alert--<?= $h($type) ?>"><?= $h($msg) ?></div>
  <?php endif; ?>
  <?= $body ?>
</section>
