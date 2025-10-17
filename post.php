<?php
declare(strict_types=1);

/**
 * post.php – vytvoření příspěvku pro přihlášeného uživatele
 * ---------------------------------------------------------
 * - vyžaduje login (viz login.php)
 * - GET  /post.php                 -> formulář
 * - POST /post.php?action=create   -> zpracování, redirect na ?action=created&id=...
 * - GET  /post.php?action=created  -> rekapitulace uloženého příspěvku
 */

use Cms\Auth\AuthService;
use Cms\Domain\Services\PostsService;
use Cms\Domain\Services\MediaService;
use Core\Files\PathResolver;
use Core\Database\Init as DB;

require_once __DIR__ . '/load.php';

cms_bootstrap_config_or_redirect();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ---------------------------------------------------------
// Pomocné funkce
// ---------------------------------------------------------
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function redirect(string $url): never { header('Location: '.$url); exit; }

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

// ---------------------------------------------------------
// Přihlášení + aktuální uživatel
// ---------------------------------------------------------
$auth = new AuthService();
$currentUser = $auth->user();
if (!$currentUser) {
    redirect('login.php'); // vyžadujeme login
}

// ---------------------------------------------------------
// Files – resolver pro uploads (baseUrl autodetekce)
// ---------------------------------------------------------
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$webBase = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$baseUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($webBase === '' ? '' : $webBase) . '/uploads';
$paths   = new PathResolver(
    baseDir: __DIR__ . '/uploads',
    baseUrl: $baseUrl
);

// ---------------------------------------------------------
// Router
// ---------------------------------------------------------
$action = (string)($_GET['action'] ?? '');
$flash  = null;

// Zpracování submitu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
    try {
        csrf_assert();

        $title   = trim((string)($_POST['title'] ?? ''));
        $type    = trim((string)($_POST['type'] ?? 'post'));
        $status  = (string)($_POST['status'] ?? 'draft');
        $content = (string)($_POST['content'] ?? '');
        $commentsAllowed = isset($_POST['comments_allowed']) ? 1 : 0;

        if ($title === '') {
            throw new RuntimeException('Název je povinný.');
        }
        if (!in_array($status, ['draft','publish'], true)) {
            $status = 'draft';
        }
        if ($type === '') $type = 'post';

        // případný upload thumbnailu
        $thumbId = null;
        if (!empty($_FILES['thumbnail']) && is_array($_FILES['thumbnail']) && (int)$_FILES['thumbnail']['error'] !== UPLOAD_ERR_NO_FILE) {
            $mediaSvc = new MediaService();
            $up = $mediaSvc->uploadAndCreate($_FILES['thumbnail'], (int)$currentUser['id'], $paths, subdir: 'posts');
            $thumbId = (int)$up['id'];
        }

        // vytvoření postu
        $posts = new PostsService();
        $postId = $posts->create([
            'title'           => $title,
            'type'            => $type,
            'status'          => $status,
            'content'         => $content,
            'author_id'       => (int)$currentUser['id'],
            'thumbnail_id'    => $thumbId,
        ]);

        // případná změna comments_allowed (naše service dává default 1; můžeme ho přepsat)
        if ($commentsAllowed === 0) {
            $posts->update($postId, ['comments_allowed' => 0]);
        }

        // redirect na rekapitulaci
        redirect((string)($_SERVER['PHP_SELF'] ?? 'post.php') . '?action=created&id=' . $postId);

    } catch (Throwable $e) {
        $flash = ['type'=>'danger','msg'=>$e->getMessage()];
    }
}

// Načtení dat pro rekapitulaci
$createdPost = null;
$createdThumb = null;
if ($action === 'created') {
    $pid = (int)($_GET['id'] ?? 0);
    if ($pid > 0) {
        $createdPost = DB::query()->table('posts','p')->select([
            'p.id','p.title','p.slug','p.type','p.status','p.author_id','p.thumbnail_id',
            'p.comments_allowed','p.published_at','p.created_at'
        ])->where('p.id','=',$pid)->first();

        if ($createdPost && !empty($createdPost['thumbnail_id'])) {
            $createdThumb = DB::query()->table('media')->select(['id','url','mime'])->where('id','=',(int)$createdPost['thumbnail_id'])->first();
        }
    }
}

// ---------------------------------------------------------
// HTML
// ---------------------------------------------------------
?>
<!doctype html>
<html lang="cs" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <title>Nový příspěvek – CMS Core</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .container-narrow { max-width: 900px; }
    .code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
    .thumb-preview { max-width: 240px; border-radius: .5rem; display:block; }
  </style>
</head>
<body class="py-4">
<div class="container container-narrow">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0">Vytvořit příspěvek</h1>
    <div class="text-end">
      <div class="small text-secondary">Přihlášen: <strong><?=h((string)$currentUser['name'])?></strong> (<?=h((string)$currentUser['email'])?>)</div>
      <a class="btn btn-sm btn-outline-secondary mt-2" href="login.php?action=logout">Odhlásit</a>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-<?=h($flash['type'])?>"><?=h($flash['msg'])?></div>
  <?php endif; ?>

  <?php if ($action === 'created' && $createdPost): ?>
    <div class="card mb-4">
      <div class="card-header">Příspěvek byl vytvořen</div>
      <div class="card-body">
        <dl class="row">
          <dt class="col-sm-3">ID</dt><dd class="col-sm-9"><?=h((string)$createdPost['id'])?></dd>
          <dt class="col-sm-3">Titulek</dt><dd class="col-sm-9"><?=h((string)$createdPost['title'])?></dd>
          <dt class="col-sm-3">Slug</dt><dd class="col-sm-9"><span class="code"><?=h((string)$createdPost['slug'])?></span></dd>
          <dt class="col-sm-3">Typ</dt><dd class="col-sm-9"><span class="badge text-bg-info"><?=h((string)$createdPost['type'])?></span></dd>
          <dt class="col-sm-3">Status</dt><dd class="col-sm-9">
            <span class="badge text-bg-<?= $createdPost['status']==='publish' ? 'success':'secondary' ?>">
              <?=h((string)$createdPost['status'])?>
            </span>
          </dd>
          <dt class="col-sm-3">Komentáře</dt><dd class="col-sm-9"><?= (int)$createdPost['comments_allowed']===1 ? 'Povoleno' : 'Zakázáno' ?></dd>
          <dt class="col-sm-3">Vytvořeno</dt><dd class="col-sm-9"><?=h((string)$createdPost['created_at'])?></dd>
        </dl>
        <?php if ($createdThumb): ?>
          <div class="mt-3">
            <div class="fw-semibold mb-2">Thumbnail</div>
            <?php if (str_starts_with((string)$createdThumb['mime'], 'image/')): ?>
              <img class="thumb-preview" src="<?=h((string)$createdThumb['url'])?>" alt="thumbnail">
              <div class="small text-secondary mt-2"><?=h((string)$createdThumb['mime'])?></div>
            <?php else: ?>
              <a href="<?=h((string)$createdThumb['url'])?>" class="btn btn-outline-primary" target="_blank">Stáhnout soubor</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <a class="btn btn-primary" href="<?=h($_SERVER['PHP_SELF'] ?? 'post.php')?>">Vytvořit další</a>
    <a class="btn btn-outline-secondary" href="index.php">Zpět na homepage</a>

  <?php else: // formulář ?>
    <form class="card" method="post" enctype="multipart/form-data" action="<?=h($_SERVER['PHP_SELF'] ?? 'post.php')?>?action=create" autocomplete="off">
      <div class="card-header">Nový příspěvek</div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label" for="title">Titulek</label>
          <input type="text" class="form-control" id="title" name="title" required>
        </div>

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label" for="type">Typ</label>
            <select class="form-select" id="type" name="type">
              <option value="post">post</option>
              <option value="page">page</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
              <option value="draft">draft</option>
              <option value="publish">publish</option>
            </select>
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="comments_allowed" name="comments_allowed" checked>
              <label class="form-check-label" for="comments_allowed">Povolit komentáře</label>
            </div>
          </div>
        </div>

        <div class="mb-3 mt-3">
          <label class="form-label" for="content">Obsah</label>
          <textarea class="form-control" id="content" name="content" rows="7" placeholder="Sem napiš text…"></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label" for="thumb">Thumbnail (obrázek/PDF)</label>
          <input class="form-control" type="file" id="thumb" name="thumbnail" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,image/*,application/pdf">
          <div class="form-text">Uloží se do <code>uploads/Y/m/posts/</code> s bezpečným názvem.</div>
        </div>

        <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      </div>
      <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary">Uložit</button>
        <a href="login.php?action=logged" class="btn btn-outline-secondary">Zpět</a>
      </div>
    </form>
  <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
