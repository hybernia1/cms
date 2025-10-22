<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array $filters */
/** @var array<int,array<string,mixed>> $items */
/** @var array{page:int,per_page:int,total:int,pages:int} $pagination */
/** @var string $csrf */
/** @var bool $webpEnabled */
/** @var callable $buildUrl */

$this->render('layouts/base', compact('pageTitle','nav','currentUser','flash'), function () use ($filters,$items,$pagination,$csrf,$webpEnabled,$buildUrl) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $currentUrl = (string)($_SERVER['REQUEST_URI'] ?? '');
  $filterType = (string)($filters['type'] ?? '');
  $filterQuery = (string)($filters['q'] ?? '');
  $page = (int)($pagination['page'] ?? 1);
  $perPage = (int)($pagination['per_page'] ?? 30);
?>
  <div
    class="media-library"
    data-media-library
    data-media-url="<?= $h($currentUrl) ?>"
    data-media-type="<?= $h($filterType) ?>"
    data-media-query="<?= $h($filterQuery) ?>"
    data-media-page="<?= $h((string)$page) ?>"
    data-media-per-page="<?= $h((string)$perPage) ?>"
  >
    <div class="media-library__overlay d-none" data-media-overlay aria-hidden="true">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Načítám…</span>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-body">
        <form class="row gy-2 gx-2 align-items-end" method="get" action="admin.php" data-media-filter-form>
          <input type="hidden" name="r" value="media">
          <div class="col-md-3">
            <label class="form-label" for="media-type">Typ</label>
            <select class="form-select form-select-sm" name="type" id="media-type">
              <option value="">Všechny</option>
              <?php foreach (['image' => 'Obrázky', 'file' => 'Soubory'] as $value => $label): ?>
                <option value="<?= $h($value) ?>" <?= $filterType === $value ? 'selected' : '' ?>><?= $h($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="media-search">Hledat (URL/MIME)</label>
            <div class="input-group input-group-sm">
              <input class="form-control" id="media-search" name="q" value="<?= $h($filterQuery) ?>" placeholder="např. .jpg nebo image/">
              <button class="btn btn-outline-secondary" type="submit" aria-label="Hledat" data-bs-toggle="tooltip" data-bs-title="Hledat">
                <i class="bi bi-search"></i>
              </button>
              <a class="btn btn-outline-secondary <?= $filterQuery === '' && $filterType === '' ? 'disabled' : '' ?>" href="admin.php?r=media" aria-label="Zrušit filtr" data-bs-toggle="tooltip" data-bs-title="Zrušit filtr">
                <i class="bi bi-x-circle"></i>
              </a>
            </div>
          </div>
          <div class="col-md-3 d-grid">
            <button class="btn btn-primary btn-sm" type="submit">Filtrovat</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
        <div>
          <h2 class="h6 text-uppercase text-secondary fw-semibold mb-1">Nahrát média</h2>
          <p class="text-secondary small mb-0">Přetáhni soubory do uploaderu nebo je vyber ručně. Soubory se uloží do <code>uploads/Y/m/media/</code>.</p>
        </div>
        <button class="btn btn-success btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#mediaUploadModal">
          <i class="bi bi-cloud-arrow-up me-1"></i>Nahrát nové soubory
        </button>
      </div>
    </div>

    <div class="card">
      <div class="card-body" data-media-grid>
        <?php $this->render('media/partials/grid', [
          'items'       => $items,
          'csrf'        => $csrf,
          'webpEnabled' => $webpEnabled,
        ]); ?>
      </div>
    </div>

    <div data-media-pagination>
      <?php $this->render('media/partials/pagination', [
        'pagination' => $pagination,
        'buildUrl'   => $buildUrl,
      ]); ?>
    </div>

    <?php $this->render('partials/media-upload-modal', [
      'modalId'          => 'mediaUploadModal',
      'title'            => 'Nahrát soubory',
      'dialogClass'      => 'modal-lg modal-dialog-centered',
      'headerCloseLabel' => 'Zavřít',
      'footerCloseLabel' => 'Zavřít',
      'form'             => [
        'id'           => 'media-upload-form',
        'action'       => 'admin.php?r=media&a=upload',
        'enctype'      => 'multipart/form-data',
        'attributes'   => [
          'data-ajax'               => '1',
          'data-media-action'       => 'upload',
          'data-media-upload-form'  => '1',
        ],
        'hiddenFields' => [
          ['name' => 'csrf', 'value' => $csrf],
        ],
        'submitButton' => [
          'id'       => 'media-upload-submit',
          'label'    => 'Nahrát',
          'icon'     => 'bi bi-cloud-arrow-up me-1',
          'class'    => 'btn btn-primary',
          'type'     => 'submit',
          'disabled' => true,
        ],
      ],
      'dropzone'         => [
        'id'              => 'media-upload-dropzone',
        'class'           => 'admin-dropzone',
        'headline'        => 'Přetáhni soubory sem nebo klikni pro výběr.',
        'headlineClass'   => 'mb-1',
        'description'     => 'Podporované formáty: JPG, PNG, GIF, WEBP, SVG, PDF, ZIP, TXT, CSV.',
        'descriptionClass'=> 'text-secondary small mb-3',
        'browseButton'    => [
          'id'    => 'media-upload-browse',
          'label' => 'Vybrat soubory',
          'class' => 'btn btn-outline-secondary btn-sm',
        ],
        'summary'         => [
          'id'    => 'media-upload-summary',
          'class' => 'text-secondary small mt-3 d-none',
        ],
        'fileInput'       => [
          'id'       => 'media-upload-input',
          'name'     => 'files[]',
          'class'    => 'd-none',
          'accept'   => '.jpg,.jpeg,.png,.webp,.gif,.svg,.pdf,.zip,.txt,.csv,image/*,application/pdf,application/zip,text/plain,text/csv',
          'multiple' => true,
        ],
        'extraInputs'     => [
          [
            'name'       => 'context',
            'value'      => '',
            'attributes' => ['data-media-context-input' => '1'],
          ],
        ],
      ],
    ]); ?>

    <div class="modal fade" id="mediaDetailModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Detail média</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zavřít"></button>
          </div>
          <div class="modal-body">
            <div class="text-center mb-3" data-role="preview"></div>
            <dl class="row small mb-0">
              <dt class="col-sm-4">ID</dt>
              <dd class="col-sm-8" data-field="id">—</dd>
              <dt class="col-sm-4">Typ</dt>
              <dd class="col-sm-8" data-field="type">—</dd>
              <dt class="col-sm-4">MIME</dt>
              <dd class="col-sm-8" data-field="mime">—</dd>
              <dt class="col-sm-4">Rozměry</dt>
              <dd class="col-sm-8" data-field="dimensions">—</dd>
              <dt class="col-sm-4">Velikost</dt>
              <dd class="col-sm-8" data-field="size">—</dd>
              <dt class="col-sm-4">Nahráno</dt>
              <dd class="col-sm-8" data-field="created">—</dd>
              <dt class="col-sm-4">Autor</dt>
              <dd class="col-sm-8" data-field="author">—</dd>
            </dl>
            <div class="mt-3" data-role="usage">
              <h6 class="small text-uppercase text-secondary fw-semibold mb-2">Využití v příspěvcích</h6>
              <div class="small" data-field="usage">Médium zatím není připojeno k žádnému příspěvku.</div>
            </div>
          </div>
          <div class="modal-footer flex-wrap gap-2">
            <a class="btn btn-light btn-sm border" target="_blank" rel="noopener" data-link="original">Otevřít originál</a>
            <a class="btn btn-light btn-sm border d-none" target="_blank" rel="noopener" data-link="webp">Otevřít WebP</a>
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Zavřít</button>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php
});
