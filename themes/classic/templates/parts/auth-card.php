<?php
/** @var string $title */
/** @var string|null $type */
/** @var string|null $msg */
/** @var string|callable $body */
?>
<section class="card card--auth">
  <h1 class="card__title"><?= e($title) ?></h1>
  <?php if (!empty($type) && !empty($msg)): ?>
    <div class="alert alert--<?= e($type) ?>"><?= e($msg) ?></div>
  <?php endif; ?>
  <?php if (is_callable($body)) { $body(); } else { echo $body; } ?>
</section>
