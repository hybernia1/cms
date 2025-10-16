<?php
/** @var callable $content */
/** @var \Cms\View\Assets $assets */
/** @var string $siteTitle */
/** @var array|null $frontUser */
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($siteTitle ?? 'Můj web', ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?= $assets->css(['assets/css/main.css']) ?>
</head>
<body>
  <?php $this->part('parts/user-bar', ['frontUser' => $frontUser ?? null]); ?>
  <div class="container">
    <?php $this->part('parts/header', ['siteTitle'=>$siteTitle ?? 'Můj web']); ?>

    <?php $content(); ?>

    <?php $this->part('parts/footer'); ?>
  </div>
  <?= $assets->js(['assets/js/app.js']) ?>
</body>
</html>
