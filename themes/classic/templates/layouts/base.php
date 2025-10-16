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
$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title><?= $h($title) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?= $assets->css(['assets/css/main.css']) ?>
</head>
<body>
  <?php $this->part('parts/user-bar', ['frontUser' => $frontUser ?? null, 'urls' => $urls]); ?>
  <div class="shell">
    <?php $this->part('parts/header', [
      'siteTitle'  => $site,
      'navigation' => $navigation ?? [],
      'urls'       => $urls,
    ]); ?>
    <main class="shell__main">
      <?php $content(); ?>
    </main>
    <?php $this->part('parts/footer', ['siteTitle' => $site, 'urls' => $urls]); ?>
  </div>
  <?= $assets->js(['assets/js/app.js']) ?>
</body>
</html>
