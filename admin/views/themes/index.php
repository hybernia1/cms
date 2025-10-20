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
  <div class="row g-3" data-themes-page data-csrf="<?= $h($csrf) ?>">
    <div class="col-lg-8">
      <div class="row g-3" data-themes-list>
        <?php foreach ($themes as $t): ?>
          <div class="col-md-6" data-theme-card data-theme-slug="<?= $h($t['slug']) ?>">
            <div class="card h-100 shadow-sm">
              <?php if ($t['screenshot']): ?>
                <img src="<?= $h($t['screenshot']) ?>" class="card-img-top" alt="screenshot" style="object-fit:cover;max-height:180px">
              <?php endif; ?>
              <div class="card-body">
                <h5 class="card-title mb-1"><?= $h($t['name']) ?></h5>
                <div class="small text-secondary mb-2">slug: <code><?= $h($t['slug']) ?></code><?php if ($t['version']): ?> • v<?= $h($t['version']) ?><?php endif; ?></div>
                <div class="small text-secondary mb-2"><?= $t['author'] ? 'Autor: '.$h($t['author']) : '' ?></div>
                <span class="badge <?= $t['hasTemplates'] ? 'text-bg-success-subtle text-success-emphasis border border-success-subtle' : 'text-bg-warning-subtle text-warning-emphasis border border-warning-subtle' ?>">
                  <?= $t['hasTemplates'] ? 'templates/ OK' : 'chybí templates/' ?>
                </span>
              </div>
              <div class="card-footer d-flex align-items-center gap-2">
                <span class="badge text-bg-primary-subtle text-primary-emphasis border border-primary-subtle<?= $t['slug'] === $activeSlug ? '' : ' d-none' ?>" data-theme-active-badge>Aktivní</span>
                <form method="post" action="admin.php?r=themes&a=activate" class="ms-auto<?= $t['slug'] === $activeSlug ? ' d-none' : '' ?>" data-ajax data-theme-activate-form>
                  <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                  <input type="hidden" name="slug" value="<?= $h($t['slug']) ?>">
                  <button class="btn btn-light btn-sm border" type="submit">Aktivovat</button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!$themes): ?>
          <div class="col-12" data-theme-empty><div class="alert alert-info">Zatím žádné šablony v <code>/themes</code>.</div></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-lg-4">
      <form class="card shadow-sm" method="post" action="admin.php?r=themes&a=upload" enctype="multipart/form-data" data-ajax data-theme-upload-form>
        <div class="card-header">Nahrát šablonu (ZIP)</div>
        <div class="card-body">
          <input type="file" class="form-control" name="theme_zip" accept=".zip,application/zip" required>
          <div class="form-text mt-2">
            ZIP musí obsahovat <code>theme.json</code> s <code>{"slug": "...", "name": "..."}</code>.
          </div>
          <div class="progress mt-3 d-none" data-theme-upload-progress>
            <div class="progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" data-theme-upload-progress-bar>0%</div>
          </div>
        </div>
        <div class="card-footer">
          <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
          <button class="btn btn-success btn-sm" type="submit">
            <i class="bi bi-cloud-upload me-1"></i>Nahrát
          </button>
        </div>
      </form>
    </div>
  </div>
<?php
});
