<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array<int,array<string,mixed>> $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
$assets = require __DIR__.'/../../assets/manifest.php';
$cssAssets = isset($assets['css']) && is_array($assets['css']) ? $assets['css'] : [];
$jsAssets = isset($assets['js']) && is_array($assets['js']) ? $assets['js'] : [];
$h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="cs" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <title><?= $h(($pageTitle ?? 'Admin').' – CMS') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php foreach ($cssAssets as $css): ?>
    <link rel="stylesheet" href="<?= $h((string)$css) ?>">
  <?php endforeach; ?>
</head>
<body>
<div class="admin-wrapper">
  <aside class="admin-sidebar" aria-label="Admin menu">
    <div class="admin-brand">
      <a href="admin.php"><i class="bi bi-wordpress"></i> CMS Admin</a>
    </div>
    <nav class="admin-menu">
      <ul>
        <?php foreach (($nav ?? []) as $item):
          $hasChildren = !empty($item['children']);
          $classes = ['admin-menu-item'];
          if ($hasChildren) { $classes[] = 'has-children'; }
          if (!empty($item['active'])) { $classes[] = 'is-active'; }
          if (!empty($item['expanded'])) { $classes[] = 'is-expanded'; }
          $href = (string)($item['href'] ?? '#');
        ?>
          <li class="<?= $h(implode(' ', $classes)) ?>">
            <a class="admin-menu-link" href="<?= $h($href) ?>">
              <?php if (!empty($item['icon'])): ?><i class="<?= $h((string)$item['icon']) ?>"></i><?php endif; ?>
              <span><?= $h((string)$item['label']) ?></span>
              <?php if ($hasChildren): ?><span class="admin-menu-caret bi bi-chevron-down"></span><?php endif; ?>
            </a>
            <?php if ($hasChildren): ?>
              <ul class="admin-submenu">
                <?php foreach ($item['children'] as $child):
                  $subClasses = ['admin-submenu-link'];
                  if (!empty($child['active'])) { $subClasses[] = 'is-active'; }
                  $childHref = (string)($child['href'] ?? '#');
                ?>
                  <li>
                    <a class="<?= $h(implode(' ', $subClasses)) ?>" href="<?= $h($childHref) ?>">
                      <?php if (!empty($child['icon'])): ?><i class="<?= $h((string)$child['icon']) ?>"></i><?php endif; ?>
                      <span><?= $h((string)$child['label']) ?></span>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>
  </aside>
  <div class="admin-sidebar-backdrop" data-admin-menu-backdrop></div>
  <div class="admin-main">
    <header class="admin-topbar">
      <div class="admin-topbar-left">
        <button class="btn btn-outline-secondary btn-sm admin-menu-toggle d-lg-none" type="button" data-admin-menu-toggle aria-label="Menu" aria-expanded="false">
          <i class="bi bi-list"></i>
        </button>
        <i class="bi bi-gear-fill"></i>
        <?php if (!empty($pageTitle)): ?>
          <span class="admin-topbar-current"><?= $h((string)$pageTitle) ?></span>
        <?php else: ?>
          <span class="admin-topbar-current">Administrace</span>
        <?php endif; ?>
      </div>
      <div class="admin-topbar-right">
        <?php if (!empty($currentUser)): ?>
          <span class="admin-user-pill">
            <span class="admin-user-name"><?= $h((string)($currentUser['name'] ?? '')) ?></span>
            <span><?= $h((string)($currentUser['email'] ?? '')) ?></span>
          </span>
        <?php endif; ?>
        <a class="btn btn-outline-secondary btn-sm" href="./" data-no-ajax>Frontend</a>
        <a class="btn btn-danger btn-sm" href="admin.php?r=auth&a=logout" data-no-ajax>Odhlásit</a>
      </div>
    </header>
    <main class="admin-content" data-flash-container>
      <?php if (!empty($pageTitle)): ?>
        <div class="admin-page-heading">
          <h1 class="admin-page-title"><?= $h((string)$pageTitle) ?></h1>
        </div>
      <?php endif; ?>
      <?php if (!empty($flash) && is_array($flash)): ?>
        <?php
          $flashType = (string)($flash['type'] ?? 'info');
          $allowedTypes = ['success','danger','warning','info'];
          if (!in_array($flashType, $allowedTypes, true)) {
              $flashType = 'info';
          }
        ?>
        <div class="alert alert-<?= $flashType ?> admin-flash" role="alert">
          <?= $h((string)($flash['msg'] ?? '')) ?>
        </div>
      <?php endif; ?>
      <?php $content(); ?>
    </main>
  </div>
</div>
<?php foreach ($jsAssets as $js): ?>
  <?php
    $src = '';
    $deferAttr = ' defer';
    if (is_array($js)) {
      $src = (string)($js['src'] ?? '');
      $deferAttr = !empty($js['defer']) ? ' defer' : '';
    } else {
      $src = (string)$js;
    }
    if ($src === '') { continue; }
  ?>
  <script src="<?= $h($src) ?>"<?= $deferAttr ?>></script>
<?php endforeach; ?>
</body>
</html>
