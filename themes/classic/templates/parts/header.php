<?php
/** @var string $siteTitle */
/** @var array<int,array<string,mixed>> $navigation */
/** @var \Cms\Utils\LinkGenerator $urls */

$items = $navigation ?: [
    ['title' => 'Domů',    'url' => $urls->home(),       'target' => '_self', 'css_class' => '', 'children' => []],
    ['title' => 'Blog',    'url' => $urls->type('post'), 'target' => '_self', 'css_class' => '', 'children' => []],
    ['title' => 'Stránky', 'url' => $urls->type('page'), 'target' => '_self', 'css_class' => '', 'children' => []],
    ['title' => 'Termy',   'url' => $urls->terms(),      'target' => '_self', 'css_class' => '', 'children' => []],
];

?>
<header class="site-header">
  <div class="site-header__branding">
    <a class="site-header__title" href="<?= e($urls->home()) ?>"><?= e($siteTitle) ?></a>
    <p class="site-header__subtitle">Moderní publikační systém v čistém PHP</p>
  </div>
  <nav class="site-nav" aria-label="Hlavní navigace">
    <?php foreach ($items as $item):
      $class = trim('site-nav__link ' . (string)($item['css_class'] ?? ''));
      ?>
      <a
        href="<?= e((string)$item['url']) ?>"
        class="<?= e($class) ?>"
        <?php if (!empty($item['target']) && $item['target'] !== '_self'): ?>target="<?= e((string)$item['target']) ?>" rel="noopener"<?php endif; ?>
      ><?= e((string)$item['title']) ?></a>
    <?php endforeach; ?>
  </nav>
</header>
