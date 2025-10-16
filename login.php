<?php
declare(strict_types=1);

/**
 * login.php – jednoduchý test přihlášení
 * ------------------------------------------------
 * - GET  /login.php                -> zobrazí formulář
 * - POST /login.php?action=login   -> pokus o přihlášení, po úspěchu redirect na ?action=logged
 * - GET  /login.php?action=logged  -> zobrazení informací o uživateli
 * - GET  /login.php?action=logout  -> odhlášení a návrat na formulář
 */

use Cms\Auth\AuthService;

require_once __DIR__ . '/load.php';

$config = require __DIR__ . '/config.php';
\Core\Database\Init::boot($config);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ---------------------------------------------------------
// Helpery
// ---------------------------------------------------------
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}
function csrf_assert(): void {
    $in = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$in)) {
        http_response_code(419);
        echo 'CSRF token invalid';
        exit;
    }
}
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

// ---------------------------------------------------------
// Router (velmi jednoduchý)
// ---------------------------------------------------------
$action = (string)($_GET['action'] ?? '');
$auth   = new AuthService();
$flash  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    try {
        csrf_assert();
        $email = trim((string)($_POST['email'] ?? ''));
        $pass  = (string)($_POST['password'] ?? '');
        if ($email === '' || $pass === '') {
            throw new RuntimeException('Vyplň e-mail i heslo.');
        }
        if (!$auth->attempt($email, $pass)) {
            throw new RuntimeException('Neplatné přihlašovací údaje nebo účet není aktivní.');
        }
        redirect((string)($_SERVER['PHP_SELF'] ?? 'login.php') . '?action=logged');
    } catch (Throwable $e) {
        $flash = ['type'=>'danger','msg'=>$e->getMessage()];
    }
}

if ($action === 'logout') {
    $auth->logout();
    redirect((string)($_SERVER['PHP_SELF'] ?? 'login.php'));
}

// ---------------------------------------------------------
// HTML (Bootstrap 5)
// ---------------------------------------------------------
?>
<!doctype html>
<html lang="cs" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <title>Login test – CMS Core</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .container-narrow { max-width: 560px; }
    .code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
  </style>
</head>
<body class="py-5">
<div class="container container-narrow">

  <h1 class="h3 mb-4">Přihlášení – test</h1>

  <?php if ($flash): ?>
    <div class="alert alert-<?=h($flash['type'])?>"><?=h($flash['msg'])?></div>
  <?php endif; ?>

  <?php if ($action === 'logged'): ?>
    <?php $user = $auth->user(); ?>
    <?php if (!$user): ?>
      <div class="alert alert-warning">Nejsi přihlášená/y. <a class="alert-link" href="<?=h($_SERVER['PHP_SELF'] ?? 'login.php')?>">Zpět na přihlášení</a>.</div>
    <?php else: ?>
      <div class="card mb-3">
        <div class="card-header">Jsi přihlášen(a)</div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-4">ID</dt><dd class="col-8"><?=h((string)$user['id'])?></dd>
            <dt class="col-4">Jméno</dt><dd class="col-8"><?=h((string)$user['name'])?></dd>
            <dt class="col-4">E-mail</dt><dd class="col-8"><?=h((string)$user['email'])?></dd>
            <dt class="col-4">Role</dt><dd class="col-8"><span class="badge text-bg-info"><?=h((string)$user['role'])?></span></dd>
            <dt class="col-4">Stav</dt><dd class="col-8"><?= ((int)$user['active']===1) ? '<span class="badge text-bg-success">aktivní</span>' : '<span class="badge text-bg-secondary">neaktivní</span>' ?></dd>
            <dt class="col-4">Vytvořen</dt><dd class="col-8"><?=h((string)($user['created_at'] ?? ''))?></dd>
          </dl>
        </div>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?=h($_SERVER['PHP_SELF'] ?? 'login.php')?>">Zpět na formulář</a>
        <a class="btn btn-danger" href="<?=h($_SERVER['PHP_SELF'] ?? 'login.php')?>?action=logout">Odhlásit</a>
      </div>
    <?php endif; ?>

  <?php else: // login form ?>
    <form class="card" method="post" action="<?=h($_SERVER['PHP_SELF'] ?? 'login.php')?>?action=login" autocomplete="off" novalidate>
      <div class="card-header">Přihlašovací formulář</div>
      <div class="card-body">
        <div class="mb-3">
          <label for="email" class="form-label">E-mail</label>
          <input type="email" class="form-control" id="email" name="email" required autofocus>
        </div>
        <div class="mb-3">
          <label for="password" class="form-label">Heslo</label>
          <input type="password" class="form-control" id="password" name="password" required minlength="8">
        </div>
        <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
        <div class="form-text">Nemáš účet? Vytvoř si admina v instalátoru nebo přímo v DB tabulce <span class="code">users</span>.</div>
      </div>
      <div class="card-footer d-flex gap-2">
        <button class="btn btn-primary" type="submit">Přihlásit</button>
        <a class="btn btn-outline-secondary" href="<?=h($_SERVER['PHP_SELF'] ?? 'login.php')?>?action=logged">Zkusit zobrazit “logged”</a>
      </div>
    </form>
  <?php endif; ?>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
