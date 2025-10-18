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
  $checked  = fn(bool $b) => $b ? 'checked' : '';
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
        'url'  => (string)($thumbRow['url'] ?? ''),
        'mime' => (string)($thumbRow['mime'] ?? ''),
      ];
    }
  }

  $workspaceKey = $isEdit && !empty($post['id'])
    ? 'post-' . (int)$post['id']
    : 'new-' . bin2hex(random_bytes(4));
  $listUrl = 'admin.php?' . http_build_query(['r' => 'posts', 'type' => $type]);
  $createUrl = 'admin.php?' . http_build_query(['r' => 'posts', 'a' => 'create', 'type' => $type]);
?>
  <div
    class="post-workspace-wrapper"
    data-post-workspace-wrapper
    data-post-type="<?= $h($type) ?>"
    data-post-create-url="<?= $h($createUrl) ?>"
    data-post-list-url="<?= $h($listUrl) ?>"
  >
    <div class="card shadow-sm mb-4 d-none" data-post-workspace-dock>
      <div class="card-body py-3">
        <div class="d-flex align-items-center flex-wrap gap-2" data-post-workspace-list>
          <div class="text-secondary small" data-post-workspace-empty>Žádné minimalizované příspěvky zatím nejsou.</div>
          <div class="d-flex align-items-center flex-wrap gap-2" data-post-workspace-cards></div>
          <button type="button" class="btn btn-outline-primary btn-sm" data-post-workspace-new>
            <i class="bi bi-plus-circle me-1"></i>Nový koncept
          </button>
        </div>
      </div>
    </div>
    <div class="post-workspace-body" data-post-workspace-body>
      <?php
        require __DIR__ . '/parts/workspace.php';
      ?>
    </div>
  </div>

  <div class="modal fade" id="mediaPickerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Vybrat nebo nahrát obrázek</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zavřít"></button>
        </div>
        <div class="modal-body">
          <ul class="nav nav-tabs" id="mediaPickerTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="media-upload-tab" data-bs-toggle="tab" data-bs-target="#media-upload-pane" type="button" role="tab">Nahrát nový</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="media-library-tab" data-bs-toggle="tab" data-bs-target="#media-library-pane" type="button" role="tab">Knihovna</button>
            </li>
          </ul>
          <div class="tab-content pt-3">
            <div class="tab-pane fade show active" id="media-upload-pane" role="tabpanel" aria-labelledby="media-upload-tab">
              <div id="media-dropzone" class="border border-dashed rounded-3 p-4 text-center bg-body-tertiary">
                <i class="bi bi-cloud-arrow-up fs-2 mb-2 d-block"></i>
                <p class="mb-2">Přetáhni soubor sem nebo klikni pro výběr.</p>
                <p class="text-secondary small mb-3">Podporované formáty: JPG, PNG, GIF, WEBP, PDF.</p>
                <input type="file" id="media-file-input" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,image/*,application/pdf" class="form-control" style="max-width:320px;margin:0 auto;">
                <p class="text-secondary small mt-3 mb-0">Po výběru potvrď tlačítkem <strong>Použít</strong>.</p>
                <div id="media-upload-preview" class="text-secondary small mt-2 d-none"></div>
              </div>
            </div>
            <div class="tab-pane fade" id="media-library-pane" role="tabpanel" aria-labelledby="media-library-tab">
              <div id="media-library-loading" class="text-center py-4 d-none">
                <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Načítání…</span></div>
              </div>
              <div id="media-library-error" class="alert alert-danger d-none"></div>
              <div id="media-library-empty" class="text-secondary text-center py-4 d-none">Žádné obrázky zatím nejsou k dispozici.</div>
              <div id="media-library-grid" class="row g-3"></div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" id="media-apply-btn" disabled data-default-label="Použít">
            <i class="bi bi-check2-circle me-1"></i><span data-label>Použít</span>
          </button>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zavřít</button>
        </div>
      </div>
    </div>
  </div>

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

  <div class="modal fade" id="contentEditorImageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Vložit obrázek</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zavřít"></button>
        </div>
        <div class="modal-body">
          <ul class="nav nav-tabs" id="content-image-tabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="content-image-upload-tab" data-bs-toggle="tab" data-bs-target="#content-image-upload-pane" type="button" role="tab">Nahrát nový</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="content-image-library-tab" data-bs-toggle="tab" data-bs-target="#content-image-library-pane" type="button" role="tab">Knihovna</button>
            </li>
          </ul>
          <div class="tab-content pt-3">
            <div class="tab-pane fade show active" id="content-image-upload-pane" role="tabpanel" aria-labelledby="content-image-upload-tab">
              <div class="border border-dashed rounded-3 p-4 text-center bg-body-tertiary" id="content-image-dropzone">
                <i class="bi bi-cloud-arrow-up fs-2 mb-2 d-block"></i>
                <p class="mb-2">Přetáhni soubor sem nebo klikni pro výběr.</p>
                <p class="text-secondary small mb-3">Podporované formáty: JPG, PNG, GIF, WEBP.</p>
                <input type="file" id="content-image-file" accept=".jpg,.jpeg,.png,.webp,.gif,image/*" class="form-control" style="max-width:320px;margin:0 auto;">
                <div id="content-image-upload-info" class="text-secondary small mt-2 d-none"></div>
              </div>
            </div>
            <div class="tab-pane fade" id="content-image-library-pane" role="tabpanel" aria-labelledby="content-image-library-tab">
              <div id="content-image-library-loading" class="text-center py-4 d-none">
                <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Načítání…</span></div>
              </div>
              <div id="content-image-library-error" class="alert alert-danger d-none"></div>
              <div id="content-image-library-empty" class="text-secondary text-center py-4 d-none">Žádné obrázky zatím nejsou k dispozici.</div>
              <div id="content-image-library-grid" class="row g-3"></div>
            </div>
          </div>
          <div class="mt-4">
            <label class="form-label" for="content-image-alt">Alternativní text</label>
            <input type="text" class="form-control" id="content-image-alt" data-image-alt placeholder="Popis obrázku">
            <div class="form-text">Pomáhá s přístupností a SEO.</div>
          </div>
          <div class="text-danger small mt-3 d-none" data-image-error></div>
        </div>
        <div class="modal-footer">
          <div class="me-auto text-secondary small" id="content-image-selected-info"></div>
          <button type="button" class="btn btn-primary" data-image-confirm disabled>Vložit</button>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zavřít</button>
        </div>
      </div>
    </div>
  </div>
<?php
});
