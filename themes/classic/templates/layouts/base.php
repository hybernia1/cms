<?php
/** @var callable $content */
/** @var \Cms\View\Assets $assets */
/** @var string $siteTitle */
/** @var string|null $pageTitle */
/** @var array|null $frontUser */
/** @var array<int,array<string,mixed>> $navigation */
/** @var \Cms\Utils\LinkGenerator $urls */

$site = $siteTitle !== '' ? $siteTitle : 'Můj web';
$title = isset($pageTitle) && $pageTitle !== ''
    ? $pageTitle . ' • ' . $site
    : $site;
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title><?= e($title) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?= $assets->css(['assets/css/main.css']) ?>
</head>
<body>
  <?php $this->part('user-bar', ['frontUser' => $frontUser ?? null, 'urls' => $urls]); ?>
  <div class="shell">
    <?php $this->part('header', [
      'siteTitle'  => $site,
      'navigation' => $navigation ?? [],
      'urls'       => $urls,
    ]); ?>
    <main class="shell__main">
      <?php $content(); ?>
    </main>
    <?php $this->part('footer', ['siteTitle' => $site, 'urls' => $urls]); ?>
  </div>
  <?= $assets->js(['assets/js/app.js']) ?>
</body>
</html>
