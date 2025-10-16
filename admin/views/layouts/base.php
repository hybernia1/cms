<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array<int,array{key:string,label:string,href:string,active:bool}> $nav */
/** @var array|null $currentUser */
$h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="cs" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <title><?= $h(($pageTitle ?? 'Admin').' – CMS') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { padding-bottom: 40px; }
    .container-narrow { max-width: 1100px; }
    .nav-link.active { background: rgba(255,255,255,.09); border-radius:.5rem; }
    .card { background: #151517; border: 1px solid #23252a; }
    .muted { color: #9aa0a6; }
  </style>
</head>
<body class="py-3">

<nav class="navbar navbar-expand-lg bg-body-tertiary mb-4">
  <div class="container container-narrow">
    <a class="navbar-brand fw-semibold" href="admin.php">CMS Admin</a>
    <div class="ms-auto d-flex align-items-center gap-2">
      <?php if (!empty($currentUser)): ?>
        <span class="small text-secondary d-none d-md-inline">
          <?= $h((string)($currentUser['name'] ?? '')) ?> (<?= $h((string)($currentUser['email'] ?? '')) ?>)
        </span>
      <?php endif; ?>
      <a class="btn btn-outline-secondary btn-sm" href="./">Frontend</a>
      <a class="btn btn-danger btn-sm" href="admin.php?r=auth&a=logout">Odhlásit</a>
    </div>
  </div>
</nav>

<div class="container container-narrow">
  <div class="row g-3">
    <aside class="col-12 col-md-3">
      <div class="list-group">
        <?php foreach (($nav ?? []) as $it): ?>
          <a class="list-group-item list-group-item-action <?= $it['active'] ? 'active' : '' ?>"
             href="<?= $h($it['href']) ?>"><?= $h($it['label']) ?></a>
        <?php endforeach; ?>
      </div>
    </aside>
    <main class="col-12 col-md-9">
      <?php $content(); ?>
    </main>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
