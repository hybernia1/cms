<?php
/** @var string $siteTitle */
/** @var array<int,array<string,mixed>> $navigation */

$items = $navigation ?: [
    ['title' => 'Domů',    'url' => './',             'target' => '_self', 'css_class' => '', 'children' => []],
    ['title' => 'Blog',    'url' => './type/post',    'target' => '_self', 'css_class' => '', 'children' => []],
    ['title' => 'Stránky', 'url' => './type/page',    'target' => '_self', 'css_class' => '', 'children' => []],
    ['title' => 'Termy',   'url' => './terms',        'target' => '_self', 'css_class' => '', 'children' => []],
];

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<header class="card">
  <div style="display:flex;align-items:center;justify-content:space-between;">
    <h1 style="margin:0"><?= $h($siteTitle) ?></h1>
    <nav class="nav">
      <?php foreach ($items as $item):
        $class = trim('nav__link ' . (string)($item['css_class'] ?? ''));
        ?>
        <a
          href="<?= $h((string)$item['url']) ?>"
          class="<?= $h($class) ?>"
          <?php if (!empty($item['target']) && $item['target'] !== '_self'): ?>target="<?= $h((string)$item['target']) ?>"<?php endif; ?>
        ><?= $h((string)$item['title']) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</header>
