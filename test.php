
<?php

/**
 * test.php — integrační test Core Database + Core Files
 * -----------------------------------------------
 * - BS5 formulář pro vytvoření příspěvku (title, slug, status, author, content, thumbnail)
 * - upload do uploads/Y/m/..., vložení do media, následně insert do posts
 * - redirect na action=edit po úspěchu
 * - fallback CREATE TABLE IF NOT EXISTS pro posts/media/users
 */

use Core\Database\Init as DB;
use Core\Files\PathResolver;
use Core\Files\Uploader;

require_once __DIR__ . '/load.php';

// --- boot DB ---
$config = require __DIR__ . '/config.php';
DB::boot($config);

// --- Files core (baseDir + baseUrl autodetekce) ---
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$webBase = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$baseUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($webBase === '' ? '' : $webBase) . '/uploads';

$paths   = new PathResolver(
    baseDir: __DIR__ . '/uploads',
    baseUrl: $baseUrl
);
$uploader = new Uploader($paths, allowedMimes: [
    'image/jpeg','image/png','image/webp','image/gif','application/pdf'
], maxBytes: 50_000_000);

// --- drobná utilita ---
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** slugify pro PHP stranu (fallback k JS) */
function make_slug(string $title): string {
    $s = mb_strtolower($title, 'UTF-8');
    $s = preg_replace('~[^\pL\d]+~u', '-', $s) ?? '';
    $s = trim($s, '-');
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    $s = preg_replace('~[^-\w]+~', '', $s) ?? '';
    return $s !== '' ? $s : 'post';
}



// --- akce (routing) ---
$action = (string)($_GET['action'] ?? 'create');
$flash  = null;

// Vytvoření / uložení
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
    try {
        $title   = trim((string)($_POST['title'] ?? ''));
        $slug    = trim((string)($_POST['slug'] ?? ''));
        $status  = (string)($_POST['status'] ?? 'draft');
        $author  = (int)($_POST['author_id'] ?? 0);
        $type    = 'post';
        $content = (string)($_POST['content'] ?? '');

        if ($title === '' || $author <= 0) {
            throw new RuntimeException('Název a autor jsou povinné.');
        }
        if ($slug === '') {
            $slug = make_slug($title);
        }
        if (!in_array($status, ['draft','publish'], true)) {
            $status = 'draft';
        }

        // thumbnail upload (volitelně)
        $thumbId = null;
        if (!empty($_FILES['thumbnail']) && is_array($_FILES['thumbnail']) && (int)$_FILES['thumbnail']['error'] !== UPLOAD_ERR_NO_FILE) {
            $info = $uploader->handle($_FILES['thumbnail'], subdir: 'posts');
            // vlož do media
            $thumbId = (int) DB::query()->table('media')->insert([
                'user_id' => $author,
                'type'    => str_starts_with($info['mime'], 'image/') ? 'image' : 'file',
                'mime'    => $info['mime'],
                'url'     => $info['url'], // ukládáme veřejnou URL
            ])->insertGetId();
        }

        // insert post
        $postId = (int) DB::query()->table('posts')->insert([
            'title'        => $title,
            'type'         => $type,
            'slug'         => $slug,
            'status'       => $status,
            'content'      => $content,
            'thumbnail_id' => $thumbId,
            'author_id'    => $author,
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ])->insertGetId();

        // redirect na edit
        header('Location: ' . ($_SERVER['PHP_SELF'] ?? 'test.php') . '?action=edit&id=' . $postId);
        exit;

    } catch (Throwable $e) {
        $flash = ['type' => 'danger', 'msg' => $e->getMessage()];
    }
}

// --- načtení dat pro formulář (seznam autorů) ---
$authors = DB::query()->table('users')->select(['id','name'])->orderBy('id','ASC')->get();

// --- pokud edit ---
$currentPost = null;
$currentMedia = null;
if ($action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $currentPost = DB::query()->table('posts','p')->select([
            'p.id','p.title','p.slug','p.status','p.content','p.thumbnail_id','p.author_id','p.created_at','p.updated_at'
        ])->where('p.id','=', $id)->first();

        if ($currentPost && !empty($currentPost['thumbnail_id'])) {
            $currentMedia = DB::query()->table('media')->select(['id','url','mime'])->where('id','=', (int)$currentPost['thumbnail_id'])->first();
        }
    }
}

// --- HTML / BS5 ---
?>
<!doctype html>
<html lang="cs" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Test Core DB + Files</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { padding-bottom: 60px; }
    .container-narrow { max-width: 960px; }
    .thumb-preview { max-width: 220px; border-radius: .5rem; display:block; }
    .code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg bg-body-tertiary mb-4">
  <div class="container container-narrow">
    <a class="navbar-brand" href="<?=h($_SERVER['PHP_SELF'] ?? 'test.php')?>">Testík: Core DB + Files</a>
    <div class="ms-auto">
      <a class="btn btn-outline-secondary btn-sm" href="<?=h($_SERVER['PHP_SELF'] ?? 'test.php')?>?action=create">Vytvořit příspěvek</a>
    </div>
  </div>
</nav>

<div class="container container-narrow">
  <?php if ($flash): ?>
    <div class="alert alert-<?=h($flash['type'])?>"><?=h($flash['msg'])?></div>
  <?php endif; ?>

  <?php if ($action === 'edit' && $currentPost): ?>
    <div class="card mb-4">
      <div class="card-header">Upravit / zobrazení příspěvku</div>
      <div class="card-body">
        <div class="row g-4">
          <div class="col-md-8">
            <dl class="row">
              <dt class="col-sm-3">ID</dt><dd class="col-sm-9"><?=h((string)$currentPost['id'])?></dd>
              <dt class="col-sm-3">Název</dt><dd class="col-sm-9"><?=h($currentPost['title'])?></dd>
              <dt class="col-sm-3">Slug</dt><dd class="col-sm-9"><span class="code"><?=h($currentPost['slug'])?></span></dd>
              <dt class="col-sm-3">Status</dt><dd class="col-sm-9"><span class="badge text-bg-<?= $currentPost['status']==='publish' ? 'success':'secondary' ?>"><?=h($currentPost['status'])?></span></dd>
              <dt class="col-sm-3">Autor</dt>
              <dd class="col-sm-9">
                <?php
                  $map = [];
                  foreach ($authors as $a) { $map[(int)$a['id']] = $a['name']; }
                  echo h($map[(int)$currentPost['author_id']] ?? ('#'.$currentPost['author_id']));
                ?>
              </dd>
              <dt class="col-sm-3">Vytvořeno</dt><dd class="col-sm-9"><?=h($currentPost['created_at'])?></dd>
              <dt class="col-sm-3">Obsah</dt><dd class="col-sm-9"><pre class="code mb-0" style="white-space:pre-wrap"><?=h($currentPost['content'] ?? '')?></pre></dd>
            </dl>
          </div>
          <div class="col-md-4">
            <div class="mb-2 fw-semibold">Thumbnail</div>
            <?php if ($currentMedia): ?>
              <?php if (str_starts_with((string)$currentMedia['mime'], 'image/')): ?>
                <img class="thumb-preview" src="<?=h((string)$currentMedia['url'])?>" alt="thumbnail">
              <?php else: ?>
                <a href="<?=h((string)$currentMedia['url'])?>" class="btn btn-outline-primary" target="_blank">Stáhnout soubor</a>
              <?php endif; ?>
              <div class="small text-secondary mt-2"><?=h($currentMedia['mime'])?></div>
            <?php else: ?>
              <div class="text-secondary">Bez náhledu.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <a class="btn btn-primary" href="<?=h($_SERVER['PHP_SELF'] ?? 'test.php')?>?action=create">Založit nový</a>

  <?php else: // create form ?>
    <form class="card" method="post" enctype="multipart/form-data" action="<?=h($_SERVER['PHP_SELF'] ?? 'test.php')?>?action=create">
      <div class="card-header">Vytvořit příspěvek</div>
      <div class="card-body">
        <div class="mb-3">
          <label for="title" class="form-label">Název</label>
          <input type="text" class="form-control" id="title" name="title" required>
          <div class="form-text">Po blur se doplní slug (můžeš přepsat).</div>
        </div>

        <div class="mb-3">
          <label for="slug" class="form-label">Slug</label>
          <input type="text" class="form-control" id="slug" name="slug" placeholder="např. moje-prvni-novinka">
        </div>

        <div class="row g-3">
          <div class="col-md-4">
            <label for="status" class="form-label">Status</label>
            <select class="form-select" id="status" name="status">
              <option value="draft">Draft</option>
              <option value="publish">Publish</option>
            </select>
          </div>
          <div class="col-md-8">
            <label for="author" class="form-label">Autor</label>
            <select class="form-select" id="author" name="author_id" required>
              <option value="" disabled selected>— vyber autora —</option>
              <?php foreach ($authors as $a): ?>
                <option value="<?=h((string)$a['id'])?>"><?=h($a['name'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="mb-3 mt-3">
          <label for="content" class="form-label">Obsah</label>
          <textarea class="form-control" id="content" name="content" rows="6" placeholder="Sem napiš text příspěvku…"></textarea>
        </div>

        <div class="mb-3">
          <label for="thumb" class="form-label">Thumbnail (obrázek / PDF)</label>
          <input class="form-control" type="file" id="thumb" name="thumbnail" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,image/*,application/pdf">
          <div class="form-text">Uloží se do <code>uploads/Y/m/</code>, název bude bezpečně přegenerovaný.</div>
        </div>
      </div>
      <div class="card-footer d-flex gap-2">
        <button class="btn btn-primary" type="submit">Publikovat</button>
        <a class="btn btn-outline-secondary" href="<?=h($_SERVER['PHP_SELF'] ?? 'test.php')?>">Zrušit</a>
      </div>
    </form>
  <?php endif; ?>
</div>

<script>
  // jednoduché slugování na frontendu
  const toSlug = (s) => {
    s = s.toLowerCase()
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '') // diakritika pryč
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
    return s || 'post';
  };
  const $title = document.querySelector('#title');
  const $slug  = document.querySelector('#slug');
  if ($title && $slug) {
    $title.addEventListener('blur', () => {
      if (!$slug.value.trim()) $slug.value = toSlug($title.value);
    });
  }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
