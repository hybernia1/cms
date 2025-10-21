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
          if (!empty($item['section'])) { $classes[] = 'is-section'; }
          $href = isset($item['href']) ? (string)$item['href'] : '';
          $sectionKey = (string)($item['key'] ?? '');
          $linkTag = 'a';
          $linkAttributes = [];
          if ($hasChildren && (empty($href) || !empty($item['section']))) {
              $linkTag = 'button';
              $linkAttributes['type'] = 'button';
              $linkAttributes['data-admin-menu-section'] = $sectionKey;
              $linkAttributes['aria-expanded'] = !empty($item['expanded']) ? 'true' : 'false';
          } else {
              $linkAttributes['href'] = $href !== '' ? $href : '#';
          }
        ?>
          <li class="<?= $h(implode(' ', $classes)) ?>">
            <<?= $linkTag ?> class="admin-menu-link"<?php foreach ($linkAttributes as $attr => $value): if ($value === null || $value === '') { continue; } ?> <?= $h($attr) ?>="<?= $h((string)$value) ?>"<?php endforeach; ?>>
              <?php if (!empty($item['icon'])): ?><i class="<?= $h((string)$item['icon']) ?>"></i><?php endif; ?>
              <span><?= $h((string)$item['label']) ?></span>
              <?php if ($hasChildren): ?><span class="admin-menu-caret bi bi-chevron-down"></span><?php endif; ?>
            </<?= $linkTag ?>>
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
          <span class="admin-user-pill" title="Přihlášený uživatel">
            <i class="bi bi-person-circle" aria-hidden="true"></i>
            <span class="admin-user-name"><?= $h((string)($currentUser['name'] ?? '')) ?></span>
          </span>
        <?php endif; ?>
        <a class="admin-icon-btn" href="./" data-no-ajax aria-label="Otevřít web" data-bs-toggle="tooltip" data-bs-title="Otevřít web">
          <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
        </a>
        <a class="admin-icon-btn" href="admin.php?r=auth&a=logout" data-no-ajax aria-label="Odhlásit" data-bs-toggle="tooltip" data-bs-title="Odhlásit">
          <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
        </a>
      </div>
    </header>
    <main class="admin-content">
      <?php if (!empty($pageTitle)): ?>
        <div class="admin-page-heading">
          <h1 class="admin-page-title"><?= $h((string)$pageTitle) ?></h1>
        </div>
      <?php endif; ?>
      <?php $content(); ?>
    </main>
  </div>
</div>
<div class="admin-flash-region" data-flash-container aria-live="polite" aria-atomic="true" role="status">
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
</div>
<div class="modal fade" id="adminConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" data-confirm-modal-title>Potvrzení akce</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zavřít"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0" data-confirm-modal-message>Opravdu chcete pokračovat?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" data-confirm-modal-cancel>Ne</button>
        <button type="button" class="btn btn-danger" data-confirm-modal-confirm>Smazat</button>
      </div>
    </div>
  </div>
</div>
<?php foreach ($jsAssets as $js): ?>
  <?php
    $src = '';
    $attributes = [];
    if (is_array($js)) {
      $src = (string)($js['src'] ?? '');
      $type = isset($js['type']) ? (string)$js['type'] : '';
      if ($type !== '') {
        $attributes[] = 'type="' . $h($type) . '"';
      }
      if (!empty($js['defer']) && $type !== 'module') {
        $attributes[] = 'defer';
      }
    } else {
      $src = (string)$js;
      $attributes[] = 'defer';
    }
    if ($src === '') { continue; }
    $attributeString = $attributes ? ' ' . implode(' ', $attributes) : '';
  ?>
  <script src="<?= $h($src) ?>"<?= $attributeString ?>></script>
<?php endforeach; ?>
</body>
</html>
