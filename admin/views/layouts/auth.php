<?php
declare(strict_types=1);
/** @var string $pageTitle */
$h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="cs" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <title><?= $h(($pageTitle ?? 'Přihlášení').' – CMS') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { min-height: 100vh; display:grid; place-items:center; background:#0f0f10; }
    .auth-card { width: min(420px, 92vw); background:#151517; border:1px solid #23252a; border-radius:12px; }
  </style>
</head>
<body>
  <main class="auth-card p-4">
    <h1 class="h4 mb-3"><?= $h($pageTitle ?? 'Přihlášení') ?></h1>
    <?php $content(); ?>
  </main>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
