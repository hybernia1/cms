<?php
/** @var string $siteTitle */
/** @var array<int,array<string,mixed>> $navigation */
/** @var \Cms\Utils\LinkGenerator $urls */

$items = $navigation ?: [
    ['title' => 'Úvod',     'url' => $urls->home(),       'target' => '_self', 'css_class' => '', 'children' => []],
    ['title' => 'Články',   'url' => $urls->type('post'), 'target' => '_self', 'css_class' => '', 'children' => []],
    ['title' => 'Stránky',  'url' => $urls->type('page'), 'target' => '_self', 'css_class' => '', 'children' => []],
    ['title' => 'Termy',    'url' => $urls->terms(),      'target' => '_self', 'css_class' => '', 'children' => []],
];

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<header class="site-header">
  <div class="site-header__branding">
    <a class="site-header__title" href="<?= $h($urls->home()) ?>"><?= $h($siteTitle) ?></a>
    <p class="site-header__subtitle">Ponořte se do obsahu – šablona Ocean Breeze.</p>
  </div>
  <nav class="site-nav" aria-label="Hlavní navigace">
    <?php foreach ($items as $item):
      $class = trim('site-nav__link ' . (string)($item['css_class'] ?? ''));
      ?>
      <a
        href="<?= $h((string)$item['url']) ?>"
        class="<?= $h($class) ?>"
        <?php if (!empty($item['target']) && $item['target'] !== '_self'): ?>target="<?= $h((string)$item['target']) ?>" rel="noopener"<?php endif; ?>
      ><?= $h((string)$item['title']) ?></a>
    <?php endforeach; ?>
  </nav>
</header>
