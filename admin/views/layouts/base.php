<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array<int,array<string,mixed>> $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
$h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="cs" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <title><?= $h(($pageTitle ?? 'Admin').' – CMS') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@yaireo/tagify@4.35.4/dist/tagify.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify@4.35.4/dist/tagify.min.js"></script>
  <style>
    :root { color-scheme: light; }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: #f0f0f1;
      color: #1d2327;
      min-height: 100vh;
    }
    a { text-decoration: none; }
    .admin-wrapper {
      display: flex;
      min-height: 100vh;
    }
    .admin-sidebar {
      width: 260px;
      background: #1d2327;
      color: #c3c4c7;
      display: flex;
      flex-direction: column;
      padding: 1.5rem 0 2rem;
    }
    .admin-brand {
      padding: 0 1.5rem 1.25rem;
      font-size: 1.1rem;
      font-weight: 600;
    }
    .admin-brand a {
      color: #fff;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }
    .admin-menu ul {
      list-style: none;
      margin: 0;
      padding: 0;
    }
    .admin-menu-item {
      position: relative;
    }
    .admin-menu-link {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.65rem 1.5rem;
      color: #c3c4c7;
      font-weight: 500;
      transition: background .2s ease, color .2s ease;
    }
    .admin-menu-link i { font-size: 1rem; }
    .admin-menu-link:hover {
      color: #fff;
      background: #23282d;
    }
    .admin-menu-item.is-active > .admin-menu-link {
      color: #fff;
      background: #2c3338;
      box-shadow: inset 4px 0 0 0 #72aee6;
    }
    .admin-menu-item.has-children > .admin-menu-link .admin-menu-caret {
      margin-left: auto;
      font-size: 0.75rem;
      transition: transform .2s ease;
    }
    .admin-menu-item.has-children.is-expanded > .admin-menu-link .admin-menu-caret,
    .admin-menu-item.has-children:hover > .admin-menu-link .admin-menu-caret {
      transform: rotate(180deg);
    }
    .admin-submenu {
      display: none;
      padding: 0.25rem 0 0.5rem;
    }
    .admin-menu-item.has-children.is-expanded > .admin-submenu,
    .admin-menu-item.has-children:hover > .admin-submenu {
      display: block;
    }
    .admin-submenu li { list-style: none; }
    .admin-submenu-link {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.4rem 1.5rem 0.4rem 2.75rem;
      color: #9aa0a6;
      font-size: 0.9rem;
      position: relative;
      transition: color .2s ease;
    }
    .admin-submenu-link:hover { color: #fff; }
    .admin-submenu-link.is-active {
      color: #fff;
      font-weight: 600;
    }
    .admin-submenu-link.is-active::before {
      content: '';
      position: absolute;
      left: 2rem;
      top: 50%;
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: #72aee6;
      transform: translateY(-50%);
    }
    .admin-main {
      flex: 1;
      display: flex;
      flex-direction: column;
      background: #f5f5f7;
    }
    .admin-topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      background: #fff;
      border-bottom: 1px solid #dcdcde;
      padding: 0.75rem 2rem;
      position: sticky;
      top: 0;
      z-index: 100;
    }
    .admin-topbar-left {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-weight: 600;
      color: #1d2327;
    }
    .admin-topbar-current {
      font-size: 0.95rem;
      color: #50575e;
    }
    .admin-topbar-right {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .admin-user-pill {
      display: flex;
      flex-direction: column;
      line-height: 1.2;
      font-size: 0.8rem;
      color: #50575e;
      margin-right: 0.5rem;
    }
    .admin-user-name { font-weight: 600; color: #1d2327; }
    .admin-content {
      flex: 1;
      padding: 2rem;
    }
    @media (min-width: 992px) {
      .admin-content { padding: 2.5rem 3rem; }
    }
    .admin-page-heading { margin-bottom: 1.5rem; }
    .admin-page-title {
      font-size: 1.75rem;
      font-weight: 600;
      margin: 0;
    }
    .admin-flash {
      margin-bottom: 1.5rem;
      border-radius: 0.75rem;
    }
    .card {
      background: #fff;
      border: 1px solid #dcdcde;
      border-radius: 0.75rem;
      box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
    }
    .card-header, .card-footer {
      background: rgba(240, 242, 245, 0.7);
      border-color: #dcdcde;
    }
    .table-dark {
      --bs-table-bg: #fff;
      --bs-table-color: #1d2327;
      --bs-table-striped-bg: #f6f7f7;
      --bs-table-striped-color: #1d2327;
      --bs-table-hover-bg: #f0f6fc;
      --bs-table-hover-color: #1d2327;
      border-color: #dcdcde;
    }
    .badge { border-radius: 999px; font-weight: 600; }
    .alert { border-radius: 0.75rem; }
    .btn-outline-secondary {
      color: #50575e;
      border-color: #c3c4c7;
    }
    .btn-outline-secondary:hover {
      color: #1d2327;
      border-color: #8c8f94;
      background: #f6f7f7;
    }
    @media (max-width: 992px) {
      .admin-wrapper { flex-direction: column; }
      .admin-sidebar { width: 100%; }
      .admin-topbar { position: static; padding: 1rem 1.5rem; }
      .admin-content { padding: 1.5rem; }
      .admin-menu-link { padding: 0.75rem 1.25rem; }
      .admin-submenu-link { padding-left: 2.25rem; }
      .admin-submenu-link.is-active::before { left: 1.6rem; }
      .admin-topbar-right { flex-wrap: wrap; justify-content: flex-end; }
    }
  </style>
</head>
<body>
<div class="admin-wrapper">
  <aside class="admin-sidebar">
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
  <div class="admin-main">
    <header class="admin-topbar">
      <div class="admin-topbar-left">
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
        <a class="btn btn-outline-secondary btn-sm" href="./">Frontend</a>
        <a class="btn btn-danger btn-sm" href="admin.php?r=auth&a=logout">Odhlásit</a>
      </div>
    </header>
    <main class="admin-content">
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
