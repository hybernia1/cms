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

$this->render('layouts/base', compact('pageTitle','nav','currentUser','flash'), function () use ($post,$csrf,$terms,$selected,$type,$types) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $isEdit = (bool)$post;
  $typeCfg = $types[$type] ?? ['create'=>'Nový příspěvek','edit'=>'Upravit příspěvek','label'=>strtoupper($type)];
  $actionParams = $isEdit
    ? ['r'=>'posts','a'=>'edit','id'=>(int)($post['id'] ?? 0),'type'=>$type]
    : ['r'=>'posts','a'=>'create','type'=>$type];
  $actionUrl = 'admin.php?'.http_build_query($actionParams);
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
        'url'  =>(string)($thumbRow['url'] ?? ''),
        'mime' => (string)($thumbRow['mime'] ?? ''),
      ];
    }
  }
?>
  <form class="card" id="post-edit-form" method="post" action="<?= $h($actionUrl) ?>" enctype="multipart/form-data">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><?= $h($isEdit ? ($typeCfg['edit'] ?? 'Upravit položku').' #'.($post['id'] ?? '') : ($typeCfg['create'] ?? 'Nová položka')) ?></span>
      <?php if ($type === 'post'): ?>
        <a class="btn btn-sm btn-outline-secondary" href="admin.php?r=terms">Správa termů</a>
      <?php endif; ?>
    </div>

    <div class="card-body">
      <div class="mb-3">
        <label class="form-label">Titulek</label>
        <input class="form-control" name="title" required value="<?= $isEdit ? $h((string)$post['title']) : '' ?>">
      </div>

      <?php if ($isEdit): ?>
        <div class="mb-3">
          <label class="form-label">Slug</label>
          <input class="form-control" name="slug" value="<?= $h((string)$post['slug']) ?>">
          <div class="form-text">Nech prázdné, pokud nechceš měnit.</div>
        </div>
      <?php endif; ?>

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Typ</label>
          <div class="form-control-plaintext fw-semibold"><?= $h((string)($typeCfg['label'] ?? strtoupper($type))) ?></div>
          <?php if ($isEdit): ?><div class="form-text">Typ nelze měnit.</div><?php endif; ?>
        </div>
        <div class="col-md-4">
          <label class="form-label">Status</label>
          <?php $curStatus = $isEdit ? (string)$post['status'] : 'draft'; ?>
          <select class="form-select" name="status">
            <option value="draft"   <?= $curStatus==='draft'?'selected':'' ?>>draft</option>
            <option value="publish" <?= $curStatus==='publish'?'selected':'' ?>>publish</option>
          </select>
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <?php $allow = $isEdit ? ((int)$post['comments_allowed']===1) : true; ?>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="comments" name="comments_allowed" <?= $checked($allow) ?>>
            <label class="form-check-label" for="comments">Povolit komentáře</label>
          </div>
        </div>
      </div>

      <?php if ($type === 'post'): ?>
        <div class="mt-3">
          <div class="mb-3">
            <label class="form-label">Kategorie</label>
            <input id="categories-input" class="form-control" placeholder="Přidej kategorie"
                   data-whitelist="<?= $encodeJson($categoriesWhitelist) ?>"
                   data-selected="<?= $encodeJson($categorySelected) ?>">
            <div class="form-text">Začni psát pro vyhledání existujících kategorií, nové potvrď klávesou Enter.</div>
            <div id="categories-hidden-inputs"></div>
          </div>
          <div class="mb-3">
            <label class="form-label">Štítky</label>
            <input id="tags-input" class="form-control" placeholder="Přidej štítky"
                   data-whitelist="<?= $encodeJson($tagsWhitelist) ?>"
                   data-selected="<?= $encodeJson($tagSelected) ?>">
            <div class="form-text">Štítky odděluj čárkou nebo potvrzuj Enterem, lze kombinovat existující i nové.</div>
            <div id="tags-hidden-inputs"></div>
          </div>
        </div>
      <?php endif; ?>

      <div class="mb-3 mt-3">
        <label class="form-label">Obsah</label>
        <textarea class="form-control" name="content" rows="8"><?= $isEdit ? $h((string)($post['content'] ?? '')) : '' ?></textarea>
      </div>

      <div class="mb-3">
        <label class="form-label">Náhledový obrázek</label>
        <div id="thumbnail-preview" class="border border-dashed rounded p-3 bg-body-tertiary d-flex flex-wrap align-items-center gap-3"
             data-empty-text="Žádný obrázek není vybrán."
             data-initial="<?= $encodeJson($currentThumb ?? null) ?>">
          <div id="thumbnail-preview-inner" class="d-flex align-items-center gap-3">
            <?php if ($currentThumb): ?>
              <?php if (str_starts_with((string)$currentThumb['mime'], 'image/')): ?>
                <img src="<?= $h((string)$currentThumb['url']) ?>" alt="Aktuální obrázek" style="max-width:220px;border-radius:.75rem">
              <?php else: ?>
                <div>
                  <div class="fw-semibold text-truncate" style="max-width:260px;"><?= $h((string)$currentThumb['url']) ?></div>
                  <div class="text-secondary small mt-1"><?= $h((string)$currentThumb['mime']) ?></div>
                </div>
              <?php endif; ?>
            <?php else: ?>
              <div class="text-secondary">Žádný obrázek není vybrán.</div>
            <?php endif; ?>
          </div>
          <div id="thumbnail-upload-info" class="text-secondary small d-none"></div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-2">
          <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#mediaPickerModal">
            <i class="bi bi-images me-1"></i>Vybrat nebo nahrát
          </button>
          <button class="btn btn-outline-danger btn-sm<?= $currentThumb ? '' : ' disabled' ?>" type="button" id="thumbnail-remove-btn">
            <i class="bi bi-x-lg me-1"></i>Odebrat
          </button>
        </div>
        <input type="hidden" name="selected_thumbnail_id" id="selected-thumbnail-id" value="<?= $currentThumb ? $h((string)$currentThumb['id']) : '' ?>">
        <input type="hidden" name="remove_thumbnail" id="remove-thumbnail" value="0">
        <input class="form-control d-none" type="file" name="thumbnail" id="thumbnail-file-input" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,image/*,application/pdf">
        <div class="form-text">Vybraný soubor se uloží do <code>uploads/Y/m/posts/</code> a lze jej přetáhnout přímo do otevřeného modálu.</div>
      </div>

  <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
  </div>
  <div class="card-footer d-flex gap-2">
    <button class="btn btn-primary" type="submit"><?= $h($isEdit ? 'Uložit změny' : 'Vytvořit') ?></button>
    <a class="btn btn-outline-secondary" href="<?= $h('admin.php?'.http_build_query(['r'=>'posts','type'=>$type])) ?>">Zpět na seznam</a>
  </div>
  </form>

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
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zavřít</button>
        </div>
      </div>
  </div>
</div>

  <script>
    (function () {
      var form = document.getElementById('post-edit-form');
      if (!form) { return; }

      var TagifyCtor = window.Tagify;

      function parseDataAttr(el, attr) {
        if (!el) return [];
        var raw = el.getAttribute(attr);
        if (!raw) return [];
        try { return JSON.parse(raw); } catch (e) { return []; }
      }

      function syncTagify(tagify, container, existingName, newName) {
        if (!tagify || !container) return;
        container.innerHTML = '';
        var newNames = [];
        tagify.value.forEach(function (item) {
          var id = item.id ? parseInt(item.id, 10) : 0;
          if (id > 0) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = existingName;
            input.value = String(id);
            container.appendChild(input);
          } else if (item.value) {
            newNames.push(item.value);
          }
        });
        var newInput = document.createElement('input');
        newInput.type = 'hidden';
        newInput.name = newName;
        newInput.value = newNames.join(', ');
        container.appendChild(newInput);
      }

      var categoryTagify = null;
      var tagTagify = null;

      if (typeof TagifyCtor === 'function') {
        var categoriesInput = document.getElementById('categories-input');
        var categoriesHidden = document.getElementById('categories-hidden-inputs');
        if (categoriesInput && categoriesHidden) {
          categoryTagify = new TagifyCtor(categoriesInput, {
            whitelist: parseDataAttr(categoriesInput, 'data-whitelist'),
            enforceWhitelist: false,
            dropdown: { enabled: 0, maxItems: 20 },
            editTags: false
          });
          var initialCategories = parseDataAttr(categoriesInput, 'data-selected');
          if (initialCategories.length) {
            categoryTagify.addTags(initialCategories);
          }
          categoryTagify.on('change', function () {
            syncTagify(categoryTagify, categoriesHidden, 'categories[]', 'new_categories');
          });
          syncTagify(categoryTagify, categoriesHidden, 'categories[]', 'new_categories');
        }

        var tagsInput = document.getElementById('tags-input');
        var tagsHidden = document.getElementById('tags-hidden-inputs');
        if (tagsInput && tagsHidden) {
          tagTagify = new TagifyCtor(tagsInput, {
            whitelist: parseDataAttr(tagsInput, 'data-whitelist'),
            enforceWhitelist: false,
            dropdown: { enabled: 0, maxItems: 20 },
            editTags: false
          });
          var initialTags = parseDataAttr(tagsInput, 'data-selected');
          if (initialTags.length) {
            tagTagify.addTags(initialTags);
          }
          tagTagify.on('change', function () {
            syncTagify(tagTagify, tagsHidden, 'tags[]', 'new_tags');
          });
          syncTagify(tagTagify, tagsHidden, 'tags[]', 'new_tags');
        }
      }

      form.addEventListener('submit', function () {
        syncTagify(categoryTagify, document.getElementById('categories-hidden-inputs'), 'categories[]', 'new_categories');
        syncTagify(tagTagify, document.getElementById('tags-hidden-inputs'), 'tags[]', 'new_tags');
      });

      // --- Media picker ---
      var modalEl = document.getElementById('mediaPickerModal');
      var previewWrapper = document.getElementById('thumbnail-preview');
      var previewInner = document.getElementById('thumbnail-preview-inner');
      var uploadInfo = document.getElementById('thumbnail-upload-info');
      var fileInput = document.getElementById('thumbnail-file-input');
      var selectedInput = document.getElementById('selected-thumbnail-id');
      var removeFlagInput = document.getElementById('remove-thumbnail');
      var removeBtn = document.getElementById('thumbnail-remove-btn');
      var dropzone = document.getElementById('media-dropzone');
      var modalFileInput = document.getElementById('media-file-input');
      var libraryTab = document.getElementById('media-library-tab');
      var libraryGrid = document.getElementById('media-library-grid');
      var libraryLoading = document.getElementById('media-library-loading');
      var libraryError = document.getElementById('media-library-error');
      var libraryEmpty = document.getElementById('media-library-empty');
      var libraryLoaded = false;

      function setRemoveEnabled(enable) {
        if (!removeBtn) return;
        removeBtn.disabled = !enable;
        if (enable) {
          removeBtn.classList.remove('disabled');
        } else {
          removeBtn.classList.add('disabled');
        }
      }

      function setPreviewPlaceholder() {
        if (!previewInner) return;
        var emptyText = previewWrapper ? previewWrapper.getAttribute('data-empty-text') : '';
        previewInner.innerHTML = '<div class="text-secondary">' + (emptyText || 'Žádný obrázek není vybrán.') + '</div>';
        if (uploadInfo) {
          uploadInfo.textContent = '';
          uploadInfo.classList.add('d-none');
        }
      }

      function setPreviewFromExisting(item) {
        if (!previewInner) return;
        var url = item && item.url ? item.url : '';
        var mime = item && item.mime ? item.mime : '';
        previewInner.innerHTML = '';
        if (mime.indexOf('image/') === 0 && url) {
          var img = document.createElement('img');
          img.src = url;
          img.alt = 'Vybraný obrázek';
          img.style.maxWidth = '220px';
          img.style.borderRadius = '.75rem';
          previewInner.appendChild(img);
        }
        var meta = document.createElement('div');
        meta.className = 'text-secondary small';
        meta.textContent = mime ? mime : (url || 'Obrázek');
        previewInner.appendChild(meta);
        if (uploadInfo) {
          uploadInfo.textContent = '';
          uploadInfo.classList.add('d-none');
        }
      }

      function setPreviewFromFile(file) {
        if (!previewInner) return;
        previewInner.innerHTML = '';
        var reader;
        if (file && file.type && file.type.indexOf('image/') === 0 && typeof FileReader !== 'undefined') {
          reader = new FileReader();
          reader.onload = function (evt) {
            previewInner.innerHTML = '';
            var img = document.createElement('img');
            img.src = evt.target && evt.target.result ? evt.target.result : '';
            img.alt = file.name;
            img.style.maxWidth = '220px';
            img.style.borderRadius = '.75rem';
            previewInner.appendChild(img);
            var meta = document.createElement('div');
            meta.className = 'text-secondary small';
            meta.textContent = file.name;
            previewInner.appendChild(meta);
          };
          reader.readAsDataURL(file);
        } else {
          var meta = document.createElement('div');
          meta.innerHTML = '<div class="fw-semibold">' + (file ? file.name : '') + '</div>' +
            '<div class="text-secondary small">' + (file && file.type ? file.type : 'Soubor') + '</div>';
          previewInner.appendChild(meta);
        }
        if (uploadInfo) {
          uploadInfo.textContent = file ? ('Připravený soubor: ' + file.name) : '';
          uploadInfo.classList.toggle('d-none', !file);
        }
      }

      function clearFileInput() {
        if (fileInput) {
          fileInput.value = '';
        }
        if (modalFileInput) {
          modalFileInput.value = '';
        }
      }

      function applyFile(file) {
        if (!file) return;
        if (fileInput) {
          try {
            var dt = new DataTransfer();
            dt.items.add(file);
            fileInput.files = dt.files;
          } catch (err) {
            // fallback: best effort
            try { fileInput.files = modalFileInput.files; } catch (e) {}
          }
        }
        if (selectedInput) selectedInput.value = '';
        if (removeFlagInput) removeFlagInput.value = '0';
        setPreviewFromFile(file);
        setRemoveEnabled(true);
        if (modalEl) {
          var modal = bootstrap.Modal.getInstance(modalEl);
          if (modal) { modal.hide(); }
        }
      }

      function selectExisting(item) {
        if (!item) return;
        clearFileInput();
        if (selectedInput) selectedInput.value = String(item.id || '');
        if (removeFlagInput) removeFlagInput.value = '0';
        setPreviewFromExisting(item);
        setRemoveEnabled(true);
        if (modalEl) {
          var modal = bootstrap.Modal.getInstance(modalEl);
          if (modal) { modal.hide(); }
        }
      }

      if (removeBtn) {
        removeBtn.addEventListener('click', function () {
          clearFileInput();
          if (selectedInput) selectedInput.value = '';
          if (removeFlagInput) removeFlagInput.value = '1';
          setPreviewPlaceholder();
          setRemoveEnabled(false);
        });
      }

      if (modalFileInput) {
        modalFileInput.addEventListener('change', function () {
          if (modalFileInput.files && modalFileInput.files[0]) {
            applyFile(modalFileInput.files[0]);
          }
        });
      }

      if (dropzone) {
        dropzone.addEventListener('dragover', function (evt) {
          evt.preventDefault();
          dropzone.classList.add('border-primary');
        });
        dropzone.addEventListener('dragleave', function () {
          dropzone.classList.remove('border-primary');
        });
        dropzone.addEventListener('drop', function (evt) {
          evt.preventDefault();
          dropzone.classList.remove('border-primary');
          if (evt.dataTransfer && evt.dataTransfer.files && evt.dataTransfer.files[0]) {
            applyFile(evt.dataTransfer.files[0]);
          }
        });
        dropzone.addEventListener('click', function () {
          if (modalFileInput) {
            modalFileInput.click();
          }
        });
      }

      function renderLibrary(items) {
        if (!libraryGrid) return;
        libraryGrid.innerHTML = '';
        if (!Array.isArray(items) || !items.length) {
          if (libraryEmpty) libraryEmpty.classList.remove('d-none');
          return;
        }
        if (libraryEmpty) libraryEmpty.classList.add('d-none');
        items.forEach(function (item) {
          var col = document.createElement('div');
          col.className = 'col-6 col-md-4 col-lg-3';
          var btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'btn btn-outline-secondary w-100 h-100 d-flex flex-column align-items-center justify-content-center gap-2 p-3';
          btn.dataset.mediaId = item.id;
          btn.dataset.mediaUrl = item.url;
          btn.dataset.mediaMime = item.mime;
          if (item.mime && item.mime.indexOf('image/') === 0 && item.url) {
            var img = document.createElement('img');
            img.src = item.url;
            img.alt = item.name || 'Obrázek';
            img.style.maxHeight = '140px';
            img.style.maxWidth = '100%';
            img.style.objectFit = 'cover';
            btn.appendChild(img);
          } else {
            var icon = document.createElement('i');
            icon.className = 'bi bi-file-earmark-text fs-2';
            btn.appendChild(icon);
            var label = document.createElement('div');
            label.className = 'text-secondary small text-truncate w-100';
            label.textContent = item.name || item.url || 'Soubor';
            btn.appendChild(label);
          }
          btn.addEventListener('click', function () {
            selectExisting(item);
          });
          col.appendChild(btn);
          libraryGrid.appendChild(col);
        });
      }

      function loadLibrary() {
        if (libraryLoaded) return;
        libraryLoaded = true;
        if (libraryLoading) libraryLoading.classList.remove('d-none');
        if (libraryError) libraryError.classList.add('d-none');
        fetch('admin.php?r=media&a=library&type=image&limit=60', {
          headers: { 'Accept': 'application/json' }
        })
          .then(function (response) {
            if (!response.ok) {
              throw new Error('Nepodařilo se načíst knihovnu médií.');
            }
            return response.json();
          })
          .then(function (data) {
            if (libraryLoading) libraryLoading.classList.add('d-none');
            renderLibrary(data && data.items ? data.items : []);
          })
          .catch(function (error) {
            if (libraryLoading) libraryLoading.classList.add('d-none');
            if (libraryError) {
              libraryError.textContent = error.message || 'Došlo k chybě při načítání médií.';
              libraryError.classList.remove('d-none');
            }
          });
      }

      if (libraryTab) {
        libraryTab.addEventListener('shown.bs.tab', loadLibrary);
      }

      // nastav výchozí stav remove tlačítka
      var hasInitial = selectedInput && selectedInput.value !== '';
      if (!hasInitial && previewWrapper) {
        var initialData = previewWrapper.getAttribute('data-initial');
        if (initialData && initialData !== 'null') {
          hasInitial = true;
        }
      }
      setRemoveEnabled(hasInitial);
    })();
  </script>
<?php
});
