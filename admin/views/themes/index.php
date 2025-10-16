<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array<int,array{slug:string,name:string,version:string,author:string,path:string,screenshot:?string,hasTemplates:bool}> $themes */
/** @var string $activeSlug */
/** @var string $csrf */

$this->render('layouts/base', compact('pageTitle','nav','currentUser','flash'), function () use ($themes,$activeSlug,$csrf) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="row g-3">
        <?php foreach ($themes as $t): ?>
          <div class="col-md-6">
            <div class="card h-100">
              <?php if ($t['screenshot']): ?>
                <img src="<?= $h($t['screenshot']) ?>" class="card-img-top" alt="screenshot" style="object-fit:cover;max-height:180px">
              <?php endif; ?>
              <div class="card-body">
                <h5 class="card-title mb-1"><?= $h($t['name']) ?></h5>
                <div class="small text-secondary mb-2">slug: <code><?= $h($t['slug']) ?></code><?php if ($t['version']): ?> • v<?= $h($t['version']) ?><?php endif; ?></div>
                <div class="small text-secondary mb-2"><?= $t['author'] ? 'Autor: '.$h($t['author']) : '' ?></div>
                <span class="badge text-bg-<?= $t['hasTemplates']?'success':'warning' ?>">
                  <?= $t['hasTemplates'] ? 'templates/ OK' : 'chybí templates/' ?>
                </span>
              </div>
              <div class="card-footer d-flex gap-2">
                <?php if ($t['slug'] === $activeSlug): ?>
                  <span class="badge text-bg-primary">Aktivní</span>
                <?php else: ?>
                  <form method="post" action="admin.php?r=themes&a=activate" class="ms-auto">
                    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                    <input type="hidden" name="slug" value="<?= $h($t['slug']) ?>">
                    <button class="btn btn-sm btn-outline-primary" type="submit">Aktivovat</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!$themes): ?>
          <div class="col-12"><div class="alert alert-info">Zatím žádné šablony v <code>/themes</code>.</div></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-lg-4">
      <form class="card" method="post" action="admin.php?r=themes&a=upload" enctype="multipart/form-data">
        <div class="card-header">Nahrát šablonu (ZIP)</div>
        <div class="card-body">
          <input type="file" class="form-control" name="theme_zip" accept=".zip,application/zip" required>
          <div class="form-text mt-2">
            ZIP musí obsahovat <code>theme.json</code> s <code>{"slug": "...", "name": "..."}</code>.
          </div>
        </div>
        <div class="card-footer">
          <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
          <button class="btn btn-primary" type="submit">Nahrát</button>
        </div>
      </form>
    </div>
  </div>
<?php
});
