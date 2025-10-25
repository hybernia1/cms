<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array<string,array<string,string>> $graphics */
/** @var string $csrf */

$this->render('parts/layouts/base', compact('pageTitle','nav','currentUser','flash'), function () use ($graphics, $csrf) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $get = static function(string $key) use ($graphics): array {
    $item = $graphics[$key] ?? [];
    return is_array($item) ? $item : [];
  };
  $favicon = $get('favicon');
  $logo = $get('logo');
  $social = $get('social_image');
?>
  <form class="card" method="post" action="admin.php?r=settings&a=graphics" id="graphicsForm" data-ajax data-form-helper="validation" enctype="multipart/form-data">
    <div class="card-body">
      <div class="alert alert-danger mb-3" data-error-for="form" hidden></div>

      <div class="mb-4">
        <h2 class="h6 text-uppercase text-secondary fw-semibold mb-3">Favicon</h2>
        <div class="row g-3 align-items-start">
          <div class="col-lg-4 col-md-6">
            <label class="form-label" for="favicon">Ikona webu</label>
            <?php $faviconUrl = (string)($favicon['url'] ?? ''); ?>
            <?php if ($faviconUrl !== ''): ?>
              <div class="d-flex align-items-center gap-2 mb-2">
                <img src="<?= $h($faviconUrl) ?>" alt="Aktuální favicon" class="rounded border" style="width: 32px; height: 32px; object-fit: contain; background: #fff;">
                <span class="text-secondary small">Aktuálně nastavená ikona</span>
              </div>
            <?php endif; ?>
            <input class="form-control" id="favicon" name="favicon" type="file" accept=".ico,image/x-icon,image/png,image/svg+xml,image/webp,image/gif,image/jpeg,image/avif">
            <?php if ($faviconUrl !== ''): ?>
              <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" id="favicon_remove" name="favicon_remove" value="1">
                <label class="form-check-label" for="favicon_remove">Odstranit aktuální faviconu</label>
              </div>
            <?php endif; ?>
            <div class="form-text">Podporované formáty: ICO, PNG, SVG, JPEG, WebP, AVIF nebo GIF. Maximální velikost 2&nbsp;MB.</div>
            <div class="invalid-feedback" data-error-for="favicon" hidden></div>
          </div>
          <div class="col-lg-8">
            <div class="alert alert-secondary h-100 mb-0">
              <p class="mb-1">Favicon se používá pro záložky a zástupce v prohlížečích.</p>
              <p class="mb-0 text-secondary small">Soubor se ukládá do složky <code>uploads/web</code>.</p>
            </div>
          </div>
        </div>
      </div>

      <div class="mb-4 border-top pt-4">
        <h2 class="h6 text-uppercase text-secondary fw-semibold mb-3">Logo webu</h2>
        <div class="row g-3 align-items-start">
          <div class="col-lg-4 col-md-6">
            <label class="form-label" for="logo">Primární logo</label>
            <?php $logoUrl = (string)($logo['url'] ?? ''); ?>
            <?php if ($logoUrl !== ''): ?>
              <div class="mb-2 p-2 border rounded bg-white" style="max-width: 220px;">
                <img src="<?= $h($logoUrl) ?>" alt="Aktuální logo" class="img-fluid" style="max-height: 120px; object-fit: contain;">
              </div>
            <?php endif; ?>
            <input class="form-control" id="logo" name="logo" type="file" accept="image/png,image/svg+xml,image/webp,image/jpeg,image/avif,image/gif">
            <?php if ($logoUrl !== ''): ?>
              <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" id="logo_remove" name="logo_remove" value="1">
                <label class="form-check-label" for="logo_remove">Odstranit aktuální logo</label>
              </div>
            <?php endif; ?>
            <div class="form-text">Doporučený formát je SVG nebo PNG s průhledným pozadím. Maximální velikost 5&nbsp;MB.</div>
            <div class="invalid-feedback" data-error-for="logo" hidden></div>
          </div>
          <div class="col-lg-8">
            <div class="alert alert-secondary h-100 mb-0">
              <p class="mb-1">Logo se hodí pro hlavičku, e-maily a další prvky brandingu.</p>
              <p class="mb-0 text-secondary small">Uloženo je opět v <code>uploads/web</code> a lze jej sdílet napříč šablonami.</p>
            </div>
          </div>
        </div>
      </div>

      <div class="mb-4 border-top pt-4">
        <h2 class="h6 text-uppercase text-secondary fw-semibold mb-3">Sdílený obrázek</h2>
        <div class="row g-3 align-items-start">
          <div class="col-lg-4 col-md-6">
            <label class="form-label" for="social_image">Obrázek pro sociální sítě</label>
            <?php $socialUrl = (string)($social['url'] ?? ''); ?>
            <?php if ($socialUrl !== ''): ?>
              <div class="mb-2 p-2 border rounded bg-white" style="max-width: 320px;">
                <img src="<?= $h($socialUrl) ?>" alt="Aktuální obrázek pro sociální sítě" class="img-fluid" style="max-height: 200px; object-fit: contain;">
              </div>
            <?php endif; ?>
            <input class="form-control" id="social_image" name="social_image" type="file" accept="image/png,image/webp,image/jpeg,image/avif,image/gif">
            <?php if ($socialUrl !== ''): ?>
              <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" id="social_image_remove" name="social_image_remove" value="1">
                <label class="form-check-label" for="social_image_remove">Odstranit aktuální obrázek</label>
              </div>
            <?php endif; ?>
            <div class="form-text">Doporučené rozměry 1200×630&nbsp;px. Maximální velikost 5&nbsp;MB.</div>
            <div class="invalid-feedback" data-error-for="social_image" hidden></div>
          </div>
          <div class="col-lg-8">
            <div class="alert alert-secondary h-100 mb-0">
              <p class="mb-1">Slouží jako výchozí Open Graph/OG obrázek při sdílení stránek na sociálních sítích.</p>
              <p class="mb-0 text-secondary small">Pokud jej šablona nepoužije automaticky, je dostupný v nastavení webu pro vlastní použití.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
      <span class="text-secondary small">Změny se projeví po uložení.</span>
      <button class="btn btn-primary" type="submit">
        <i class="bi bi-image me-1"></i>Uložit grafiku
      </button>
    </div>
  </form>
<?php
});
