<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array|null $post */
/** @var string $csrf */
/** @var array{category:array<int,array{id:int,name:string,slug:string,type:string}>,tag:array<int,array{id:int,name:string,slug:string,type:string}>} $terms */
/** @var array{category:array<int,int>,tag:array<int,int>} $selected */
/** @var string $type */
/** @var array $types */
/** @var array<int> $attachedMedia */

$this->render('layouts/base', compact('pageTitle','nav','currentUser','flash'), function () use ($post,$csrf,$terms,$selected,$type,$types) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $isEdit = (bool)$post;
  $typeCfg = $types[$type] ?? ['create'=>'Nový příspěvek','edit'=>'Upravit příspěvek','label'=>strtoupper($type)];
  $actionParams = $isEdit
    ? ['r'=>'posts','a'=>'edit','id'=>(int)($post['id'] ?? 0),'type'=>$type]
    : ['r'=>'posts','a'=>'create','type'=>$type];
  $actionUrl = 'admin.php?'.http_build_query($actionParams);
  $autosaveUrl = 'admin.php?'.http_build_query(['r' => 'posts', 'a' => 'autosave', 'type' => $type]);
  $mediaLibraryUrl = 'admin.php?'.http_build_query(['r' => 'media', 'a' => 'library', 'type' => 'image', 'limit' => 60]);
  $checked  = fn(bool $b) => $b ? 'checked' : '';
  $currentStatus = $isEdit ? (string)($post['status'] ?? 'draft') : 'draft';
  $statusLabels = ['draft' => 'Koncept', 'publish' => 'Publikováno'];
  $currentStatusLabel = $statusLabels[$currentStatus] ?? ucfirst($currentStatus);
  $commentsAllowed = false;
  if ($type === 'post') {
    $commentsAllowed = $isEdit ? ((int)($post['comments_allowed'] ?? 1) === 1) : true;
  }
  $typeLabel = (string)($typeCfg['label'] ?? strtoupper($type));
  $encodeJson = function ($value) use ($h): string {
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
      $json = '[]';
    }
    return $h($json);
  };

  $prepareTermList = function (array $items): array {
    $out = [];
    foreach ($items as $item) {
      $id = (int)($item['id'] ?? 0);
      $name = trim((string)($item['name'] ?? ''));
      if ($name === '') {
        $name = 'ID ' . $id;
      }
      $out[] = [
        'id'    => $id,
        'value' => $name,
        'slug'  => (string)($item['slug'] ?? ''),
      ];
    }
    return $out;
  };

  $categoriesWhitelist = $prepareTermList($terms['category'] ?? []);
  $tagsWhitelist = $prepareTermList($terms['tag'] ?? []);

  $categorySelectedIds = array_map('intval', $selected['category'] ?? []);
  $tagSelectedIds = array_map('intval', $selected['tag'] ?? []);

  $findSelectedItems = function (array $whitelist, array $selectedIds): array {
    $map = [];
    foreach ($whitelist as $item) {
      $map[$item['id']] = $item;
    }
    $result = [];
    foreach ($selectedIds as $id) {
      if (isset($map[$id])) {
        $result[] = $map[$id];
      } else {
        $result[] = ['id'=>$id,'value'=>'ID ' . $id,'slug'=>''];
      }
    }
    return $result;
  };

  $categorySelected = $findSelectedItems($categoriesWhitelist, $categorySelectedIds);
  $tagSelected = $findSelectedItems($tagsWhitelist, $tagSelectedIds);

  $currentThumb = null;
  if ($isEdit && !empty($post['thumbnail_id'])) {
    $thumbRow = \Core\Database\Init::query()->table('media')->select(['id','url','mime'])->where('id','=',(int)$post['thumbnail_id'])->first();
    if ($thumbRow) {
      $currentThumb = [
        'id'   => (int)$thumbRow['id'],
        'url'  =>(string)($thumbRow['url'] ?? ''),
        'mime' => (string)($thumbRow['mime'] ?? ''),
      ];
    }
  }
?>
  <div data-post-editor-root>
    <form
      id="post-edit-form"
      class="post-edit-form"
      method="post"
      action="<?= $h($actionUrl) ?>"
      enctype="multipart/form-data"
      data-ajax
      data-form-helper="validation"
      data-autosave-form="1"
      data-autosave-url="<?= $h($autosaveUrl) ?>"
      data-post-type="<?= $h($type) ?>"
      data-post-id="<?= $isEdit ? $h((string)($post['id'] ?? '')) : '' ?>"
      data-post-editor
    >
      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
      <input type="hidden" name="status" id="status-input" value="<?= $h($currentStatus) ?>" data-post-status-input>
    <div class="row g-4 align-items-start">
      <div class="col-xl-8">
        <div class="card shadow-sm">
          <div class="card-body p-4">
            <div class="alert alert-danger mb-4" data-error-for="form" id="post-form-error" hidden></div>
            <div class="mb-4">
              <label class="form-label fs-5">Titulek</label>
              <input class="form-control form-control-lg" name="title" required value="<?= $isEdit ? $h((string)$post['title']) : '' ?>">
              <div class="invalid-feedback" data-error-for="title" id="post-title-error" hidden></div>
            </div>

            <?php if ($isEdit): ?>
              <div class="mb-4">
                <label class="form-label">Slug</label>
                <input class="form-control" name="slug" value="<?= $h((string)$post['slug']) ?>">
                <div class="form-text">Nech prázdné, pokud nechceš měnit.</div>
                <div class="invalid-feedback" data-error-for="slug" id="post-slug-error" hidden></div>
              </div>
            <?php endif; ?>

            <div>
              <label class="form-label fs-5">Obsah</label>
              <textarea
                class="form-control"
                name="content"
                rows="12"
                data-content-editor
                data-placeholder="Začni psát obsah…"
                data-attachments-input="#attached-media-input"
                data-post-id="<?= $isEdit ? $h((string)($post['id'] ?? '')) : '' ?>"
              ><?= $isEdit ? $h((string)($post['content'] ?? '')) : '' ?></textarea>
              <input type="hidden" name="attached_media" id="attached-media-input" value="<?= !empty($attachedMedia) ? $encodeJson($attachedMedia) : '' ?>">
              <div class="invalid-feedback" data-error-for="content" id="post-content-error" hidden></div>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4">
          <a class="btn btn-outline-secondary" href="<?= $h('admin.php?'.http_build_query(['r' => 'posts', 'type' => $type])) ?>">Zpět na seznam</a>
          <span class="text-secondary small<?= $isEdit ? '' : ' d-none' ?>" data-post-id-display><?= $isEdit ? 'ID #' . $h((string)($post['id'] ?? '')) : '' ?></span>
        </div>
      </div>

      <div class="col-xl-4">
        <div class="vstack gap-3">
          <div class="card shadow-sm">
            <div class="card-header fw-semibold">Publikovat</div>
            <div class="card-body">
              <div class="mb-3">
                <div class="text-secondary small mb-1">Typ obsahu</div>
                <div class="badge text-bg-light text-uppercase"><?= $h($typeLabel) ?></div>
              </div>
              <div class="mb-4">
                <div class="text-secondary small mb-1">Aktuální stav</div>
                <div id="status-current-label" class="fw-semibold text-capitalize" data-status-labels='<?= $encodeJson($statusLabels) ?>' data-post-status-label><?= $h($currentStatusLabel) ?></div>
              </div>
              <div class="text-secondary small" data-autosave-status aria-live="polite"></div>
            </div>
            <div class="card-footer d-flex flex-wrap gap-2">
              <button class="btn btn-outline-secondary btn-sm" type="submit" data-status-value="draft">Uložit koncept</button>
              <button class="btn btn-primary btn-sm" type="submit" data-status-value="publish">Publikovat</button>
            </div>
          </div>

          <?php if ($type === 'post'): ?>
            <div class="card shadow-sm">
              <div class="card-header fw-semibold">Kategorie</div>
              <div class="card-body">
                <div
                  data-tag-field
                  data-existing-name="categories[]"
                  data-new-name="new_categories"
                  data-placeholder="Přidej kategorie"
                  data-helper="Začni psát pro vyhledání existujících kategorií, nové potvrď klávesou Enter."
                  data-empty="Žádné kategorie nejsou vybrány."
                  data-whitelist="<?= $encodeJson($categoriesWhitelist) ?>"
                  data-selected="<?= $encodeJson($categorySelected) ?>"
                >
                  <div data-tag-hidden></div>
                </div>
              </div>
            </div>
            <div class="card shadow-sm">
              <div class="card-header fw-semibold">Štítky</div>
              <div class="card-body">
                <div
                  data-tag-field
                  data-existing-name="tags[]"
                  data-new-name="new_tags"
                  data-placeholder="Přidej štítky"
                  data-helper="Štítky odděluj čárkou nebo potvrzuj Enterem, lze kombinovat existující i nové."
                  data-empty="Žádné štítky nejsou vybrány."
                  data-whitelist="<?= $encodeJson($tagsWhitelist) ?>"
                  data-selected="<?= $encodeJson($tagSelected) ?>"
                >
                  <div data-tag-hidden></div>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <div class="card shadow-sm">
            <div class="card-header fw-semibold">Média</div>
            <div class="card-body">
              <div
                id="thumbnail-preview"
                class="border border-dashed rounded p-3 bg-body-tertiary d-flex flex-wrap align-items-center gap-3"
                data-empty-text="Žádný obrázek není vybrán."
                data-initial="<?= $encodeJson($currentThumb ?? null) ?>"
                data-thumbnail-preview
              >
                <div id="thumbnail-preview-inner" class="d-flex align-items-center gap-3" data-thumbnail-preview-inner>
                  <?php if ($currentThumb): ?>
                    <?php if (str_starts_with((string)$currentThumb['mime'], 'image/')): ?>
                      <img src="<?= $h((string)$currentThumb['url']) ?>" alt="Aktuální obrázek" style="max-width:220px;border-radius:.75rem">
                    <?php else: ?>
                      <div>
                        <div class="fw-semibold text-truncate" style="max-width:260px;">
                          <?= $h((string)$currentThumb['url']) ?>
                        </div>
                        <div class="text-secondary small mt-1">
                          <?= $h((string)$currentThumb['mime']) ?>
                        </div>
                      </div>
                    <?php endif; ?>
                  <?php else: ?>
                    <div class="text-secondary">Žádný obrázek není vybrán.</div>
                  <?php endif; ?>
                </div>
                <div id="thumbnail-upload-info" class="text-secondary small d-none" data-thumbnail-upload-info></div>
              </div>
              <div class="d-flex flex-wrap gap-2 mt-2">
                <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#mediaPickerModal">
                  <i class="bi bi-images me-1"></i>Vybrat nebo nahrát
                </button>
                <button class="btn btn-outline-danger btn-sm<?= $currentThumb ? '' : ' disabled' ?>" type="button" id="thumbnail-remove-btn" data-thumbnail-remove>
                  <i class="bi bi-x-lg me-1"></i>Odebrat
                </button>
              </div>
              <input type="hidden" name="selected_thumbnail_id" id="selected-thumbnail-id" value="<?= $currentThumb ? $h((string)$currentThumb['id']) : '' ?>" data-thumbnail-selected-input>
              <input type="hidden" name="remove_thumbnail" id="remove-thumbnail" value="0" data-thumbnail-remove-input>
              <input class="form-control d-none" type="file" name="thumbnail" id="thumbnail-file-input" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,image/*,application/pdf" data-thumbnail-file-input>
              <div class="form-text mt-2">Vybraný soubor se uloží do <code>uploads/Y/m/posts/</code> a lze jej přetáhnout přímo do otevřeného modálu.</div>
            </div>
          </div>

          <?php if ($type === 'post'): ?>
            <div class="card shadow-sm">
              <div class="card-header fw-semibold">Nastavení</div>
              <div class="card-body">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="comments" name="comments_allowed" <?= $checked($commentsAllowed) ?>>
                  <label class="form-check-label" for="comments">Povolit komentáře</label>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    </form>


    <?php $this->render('partials/media-picker-modal', [
      'modalId'          => 'mediaPickerModal',
      'title'            => 'Vybrat nebo nahrát obrázek',
      'dialogClass'      => 'modal-xl modal-dialog-scrollable',
      'modalAttributes'  => [
        'data-media-picker-context'     => 'thumbnail',
        'data-media-picker-library-url' => $mediaLibraryUrl,
      ],
      'tabs'             => [
        'upload'  => 'Nahrát nový',
        'library' => 'Knihovna',
      ],
      'upload'           => [
        'headline'     => 'Přetáhni soubor sem nebo klikni pro výběr.',
        'description'  => 'Podporované formáty: JPG, PNG, GIF, WEBP, PDF.',
        'input'        => [
          'accept' => '.jpg,.jpeg,.png,.webp,.gif,.pdf,image/*,application/pdf',
          'class'  => 'form-control',
          'style'  => 'max-width:320px;margin:0 auto;',
        ],
        'summaryClass' => 'text-secondary small mt-2 d-none',
        'note'         => 'Po výběru potvrď tlačítkem „Použít“.',
      ],
      'library'          => [
        'emptyText' => 'Žádné obrázky zatím nejsou k dispozici.',
      ],
      'applyButton'      => [
        'defaultLabel' => 'Použít',
        'uploadLabel'  => 'Použít nahraný soubor',
        'libraryLabel' => 'Vybrat z knihovny',
        'icon'         => 'bi bi-check2-circle me-1',
      ],
      'headerCloseLabel' => 'Zavřít',
      'footerCloseLabel' => 'Zavřít',
    ]); ?>

  <div class="modal fade" id="contentEditorLinkModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Vložit odkaz</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zavřít"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="content-link-url">URL odkazu</label>
            <input type="url" class="form-control" id="content-link-url" data-link-url placeholder="https://" autocomplete="off">
          </div>
          <div class="form-check mb-0">
            <input class="form-check-input" type="checkbox" id="content-link-newtab" data-link-target>
            <label class="form-check-label" for="content-link-newtab">Otevřít v nové záložce</label>
          </div>
          <div class="text-danger small mt-2 d-none" data-link-error></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" data-link-confirm>Vložit</button>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zavřít</button>
        </div>
      </div>
    </div>
  </div>

  <?php $this->render('partials/media-picker-modal', [
    'modalId'          => 'contentEditorImageModal',
    'title'            => 'Vložit obrázek',
    'dialogClass'      => 'modal-lg modal-dialog-scrollable',
    'modalAttributes'  => [
      'data-media-picker-context'     => 'content-image',
      'data-media-picker-library-url' => 'admin.php?r=media&a=library&type=image&limit=60',
    ],
    'tabs'             => [
      'upload'  => 'Nahrát nový',
      'library' => 'Knihovna',
    ],
    'upload'           => [
      'headline'     => 'Přetáhni soubor sem nebo klikni pro výběr.',
      'description'  => 'Podporované formáty: JPG, PNG, GIF, WEBP.',
      'input'        => [
        'accept' => '.jpg,.jpeg,.png,.webp,.gif,image/*',
        'class'  => 'form-control',
        'style'  => 'max-width:320px;margin:0 auto;',
      ],
      'summaryClass' => 'text-secondary small mt-2 d-none',
    ],
    'library'          => [
      'emptyText' => 'Žádné obrázky zatím nejsou k dispozici.',
    ],
    'applyButton'      => [
      'defaultLabel' => 'Vložit',
      'uploadLabel'  => 'Nahrát a vložit',
      'libraryLabel' => 'Vložit z knihovny',
      'attributes'   => ['data-image-confirm' => '1'],
    ],
    'afterTabs'        => function (): void { ?>
      <div class="mt-4">
        <label class="form-label" for="content-image-alt">Alternativní text</label>
        <input type="text" class="form-control" id="content-image-alt" data-image-alt placeholder="Popis obrázku">
        <div class="form-text">Pomáhá s přístupností a SEO.</div>
      </div>
      <div class="text-danger small mt-3 d-none" data-image-error></div>
    <?php },
    'footerBeforeButtons' => function (): void { ?>
      <div class="me-auto text-secondary small" data-image-selected-info></div>
    <?php },
    'headerCloseLabel' => 'Zavřít',
    'footerCloseLabel' => 'Zavřít',
  ]); ?>
  </div>
<?php
});
