<?php
declare(strict_types=1);
/**
 * @var callable $h
 * @var bool $isEdit
 * @var array|null $post
 * @var string $csrf
 * @var array{category:array<int,array{id:int,name:string,slug:string,type:string}>,tag:array<int,array{id:int,name:string,slug:string,type:string}>} $terms
 * @var array{category:array<int,int>,tag:array<int,int>} $selected
 * @var string $type
 * @var array $types
 * @var array<int> $attachedMedia
 * @var string $workspaceKey
 * @var array $categoriesWhitelist
 * @var array $categorySelected
 * @var array $tagsWhitelist
 * @var array $tagSelected
 * @var array|null $currentThumb
 * @var callable $checked
 * @var callable $encodeJson
 */

$workspaceTypeLabel = (string)($types[$type]['label'] ?? strtoupper($type));
$formActionParams = $isEdit
  ? ['r' => 'posts', 'a' => 'edit', 'id' => (int)($post['id'] ?? 0), 'type' => $type]
  : ['r' => 'posts', 'a' => 'create', 'type' => $type];
$actionUrl = 'admin.php?' . http_build_query($formActionParams);
$autosaveUrl = 'admin.php?' . http_build_query(['r' => 'posts', 'a' => 'autosave', 'type' => $type]);
$statusLabels = ['draft' => 'Koncept', 'publish' => 'Publikováno'];
$currentStatus = $isEdit ? (string)($post['status'] ?? 'draft') : 'draft';
$currentStatusLabel = $statusLabels[$currentStatus] ?? ucfirst($currentStatus);
$commentsAllowed = $isEdit ? ((int)($post['comments_allowed'] ?? 1) === 1) : true;
$formIds = [
  'form' => $workspaceKey . '-form',
  'statusInput' => $workspaceKey . '-status-input',
  'statusLabel' => $workspaceKey . '-status-label',
  'thumbnailPreview' => $workspaceKey . '-thumbnail-preview',
  'thumbnailPreviewInner' => $workspaceKey . '-thumbnail-preview-inner',
  'thumbnailUploadInfo' => $workspaceKey . '-thumbnail-upload-info',
  'thumbnailFileInput' => $workspaceKey . '-thumbnail-file-input',
  'thumbnailSelected' => $workspaceKey . '-selected-thumbnail-id',
  'thumbnailRemoveFlag' => $workspaceKey . '-remove-thumbnail',
  'thumbnailRemoveBtn' => $workspaceKey . '-thumbnail-remove-btn',
  'mediaDropzone' => $workspaceKey . '-media-dropzone',
  'mediaUploadPreview' => $workspaceKey . '-media-upload-preview',
  'mediaApplyBtn' => $workspaceKey . '-media-apply-btn',
  'attachedMedia' => $workspaceKey . '-attached-media',
  'commentsToggle' => $workspaceKey . '-comments-toggle',
];
$workspaceTitle = $isEdit ? trim((string)($post['title'] ?? '')) : '';
$workspaceTitleDisplay = $workspaceTitle !== '' ? $workspaceTitle : 'Nový koncept';
$workspacePostId = $isEdit ? (int)($post['id'] ?? 0) : 0;
?>
<div
  class="post-workspace"
  data-post-workspace
  data-workspace-id="<?= $h($workspaceKey) ?>"
  data-post-type="<?= $h($type) ?>"
  data-post-status="<?= $h($currentStatus) ?>"
  data-post-title="<?= $h($workspaceTitleDisplay) ?>"
  <?php if ($workspacePostId > 0): ?>data-post-id="<?= $h((string)$workspacePostId) ?>"<?php endif; ?>
>
  <div class="card shadow-sm mb-4 border-0 bg-body-tertiary">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div>
        <div class="text-secondary small" data-post-workspace-meta><?= $h($workspaceTypeLabel) ?></div>
        <div class="fw-semibold" data-post-workspace-title><?= $h($workspaceTitleDisplay) ?></div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-post-workspace-minimize>
          <i class="bi bi-dash-lg me-1"></i>Minimalizovat
        </button>
        <button type="button" class="btn btn-outline-danger btn-sm" data-post-workspace-close>
          <i class="bi bi-x-lg me-1"></i>Zavřít
        </button>
      </div>
    </div>
  </div>

  <form
    id="<?= $h($formIds['form']) ?>"
    class="post-edit-form"
    method="post"
    action="<?= $h($actionUrl) ?>"
    enctype="multipart/form-data"
    data-ajax
    data-autosave-form="1"
    data-autosave-url="<?= $h($autosaveUrl) ?>"
    data-post-type="<?= $h($type) ?>"
    data-post-id="<?= $isEdit ? $h((string)($post['id'] ?? '')) : '' ?>"
  >
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
    <input type="hidden" name="status" id="<?= $h($formIds['statusInput']) ?>" value="<?= $h($currentStatus) ?>">
    <div class="row g-4 align-items-start">
      <div class="col-xl-8">
        <div class="card shadow-sm">
          <div class="card-body p-4">
            <div class="mb-4">
              <label class="form-label fs-5">Titulek</label>
              <input class="form-control form-control-lg" name="title" required value="<?= $isEdit ? $h((string)$post['title']) : '' ?>" data-post-workspace-title-input>
            </div>

            <?php if ($isEdit): ?>
              <div class="mb-4">
                <label class="form-label">Slug</label>
                <input class="form-control" name="slug" value="<?= $h((string)$post['slug']) ?>">
                <div class="form-text">Nech prázdné, pokud nechceš měnit.</div>
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
                data-attachments-input="#<?= $h($formIds['attachedMedia']) ?>"
                data-post-id="<?= $isEdit ? $h((string)($post['id'] ?? '')) : '' ?>"
              ><?= $isEdit ? $h((string)($post['content'] ?? '')) : '' ?></textarea>
              <input type="hidden" name="attached_media" id="<?= $h($formIds['attachedMedia']) ?>" value="<?= !empty($attachedMedia) ? $encodeJson($attachedMedia) : '' ?>">
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
                <div class="badge text-bg-light text-uppercase"><?= $h($workspaceTypeLabel) ?></div>
              </div>
              <div class="mb-4">
                <div class="text-secondary small mb-1">Aktuální stav</div>
                <div id="<?= $h($formIds['statusLabel']) ?>" class="fw-semibold text-capitalize" data-status-labels='<?= $encodeJson($statusLabels) ?>' data-post-workspace-status-label><?= $h($currentStatusLabel) ?></div>
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
              <div id="<?= $h($formIds['thumbnailPreview']) ?>" class="border border-dashed rounded p-3 bg-body-tertiary d-flex flex-wrap align-items-center gap-3"
                   data-empty-text="Žádný obrázek není vybrán."
                   data-initial="<?= $encodeJson($currentThumb ?? null) ?>">
                <div id="<?= $h($formIds['thumbnailPreviewInner']) ?>" class="d-flex align-items-center gap-3">
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
                <div id="<?= $h($formIds['thumbnailUploadInfo']) ?>" class="text-secondary small d-none"></div>
              </div>
              <div class="d-flex flex-wrap gap-2 mt-2">
                <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#mediaPickerModal">
                  <i class="bi bi-images me-1"></i>Vybrat nebo nahrát
                </button>
                <button class="btn btn-outline-danger btn-sm<?= $currentThumb ? '' : ' disabled' ?>" type="button" id="<?= $h($formIds['thumbnailRemoveBtn']) ?>">
                  <i class="bi bi-x-lg me-1"></i>Odebrat
                </button>
              </div>
              <input type="hidden" name="selected_thumbnail_id" id="<?= $h($formIds['thumbnailSelected']) ?>" value="<?= $currentThumb ? $h((string)$currentThumb['id']) : '' ?>">
              <input type="hidden" name="remove_thumbnail" id="<?= $h($formIds['thumbnailRemoveFlag']) ?>" value="0">
              <input class="form-control d-none" type="file" name="thumbnail" id="<?= $h($formIds['thumbnailFileInput']) ?>" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,image/*,application/pdf">
              <div class="form-text mt-2">Vybraný soubor se uloží do <code>uploads/Y/m/posts/</code> a lze jej přetáhnout přímo do otevřeného modálu.</div>
            </div>
          </div>

          <div class="card shadow-sm">
            <div class="card-header fw-semibold">Nastavení</div>
            <div class="card-body">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="<?= $h($formIds['commentsToggle']) ?>" name="comments_allowed" <?= $checked($commentsAllowed) ?>>
                <label class="form-check-label" for="<?= $h($formIds['commentsToggle']) ?>">Povolit komentáře</label>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </form>

  <script>
    (function () {
      var form = document.getElementById('<?= $h($formIds['form']) ?>');
      if (!form) { return; }

      var statusInput = document.getElementById('<?= $h($formIds['statusInput']) ?>');
      var statusLabel = document.getElementById('<?= $h($formIds['statusLabel']) ?>');
      var statusMap = {};
      if (statusLabel) {
        var rawMap = statusLabel.getAttribute('data-status-labels');
        if (rawMap) {
          try { statusMap = JSON.parse(rawMap); } catch (e) { statusMap = {}; }
        }
      }
      var statusButtons = form.querySelectorAll('[data-status-value]');
      statusButtons.forEach(function (button) {
        button.addEventListener('click', function () {
          var value = button.getAttribute('data-status-value') || '';
          if (statusInput) {
            statusInput.value = value;
            try {
              var changeEvent = new Event('change', { bubbles: true });
              statusInput.dispatchEvent(changeEvent);
            } catch (err) {}
          }
          if (statusLabel) {
            var label = statusMap && statusMap[value] ? statusMap[value] : value;
            if (label) {
              statusLabel.textContent = label;
              try {
                form.dispatchEvent(new CustomEvent('cms:workspace:status-preview', { detail: { status: value, label: label } }));
              } catch (err) {}
            }
          }
        });
      });

      var modalEl = document.getElementById('mediaPickerModal');
      var previewWrapper = document.getElementById('<?= $h($formIds['thumbnailPreview']) ?>');
      var previewInner = document.getElementById('<?= $h($formIds['thumbnailPreviewInner']) ?>');
      var uploadInfo = document.getElementById('<?= $h($formIds['thumbnailUploadInfo']) ?>');
      var fileInput = document.getElementById('<?= $h($formIds['thumbnailFileInput']) ?>');
      var selectedInput = document.getElementById('<?= $h($formIds['thumbnailSelected']) ?>');
      var removeFlagInput = document.getElementById('<?= $h($formIds['thumbnailRemoveFlag']) ?>');
      var removeBtn = document.getElementById('<?= $h($formIds['thumbnailRemoveBtn']) ?>');
      var dropzone = document.getElementById('<?= $h($formIds['mediaDropzone']) ?>');
      var modalFileInput = document.getElementById('media-file-input');
      var libraryTab = document.getElementById('media-library-tab');
      var libraryGrid = document.getElementById('media-library-grid');
      var libraryLoading = document.getElementById('media-library-loading');
      var libraryError = document.getElementById('media-library-error');
      var libraryEmpty = document.getElementById('media-library-empty');
      var uploadPreview = document.getElementById('<?= $h($formIds['mediaUploadPreview']) ?>');
      var applyBtn = document.getElementById('<?= $h($formIds['mediaApplyBtn']) ?>');
      var applyBtnLabel = applyBtn ? applyBtn.querySelector('[data-label]') : null;
      var defaultApplyLabel = applyBtn ? (applyBtn.getAttribute('data-default-label') || (applyBtnLabel ? applyBtnLabel.textContent : 'Použít')) : 'Použít';
      var pendingFile = null;
      var pendingLibraryItem = null;
      var libraryLoaded = false;

      function updateApplyButton(label, enabled) {
        if (!applyBtn) return;
        var text = label || defaultApplyLabel;
        if (applyBtnLabel) {
          applyBtnLabel.textContent = text;
        } else {
          applyBtn.textContent = text;
        }
        applyBtn.disabled = !enabled;
      }

      function clearLibrarySelection() {
        if (!libraryGrid) return;
        var activeButtons = libraryGrid.querySelectorAll('button.active');
        activeButtons.forEach(function (btn) {
          btn.classList.remove('active');
          btn.removeAttribute('aria-pressed');
        });
      }

      function resetPendingSelection() {
        pendingFile = null;
        pendingLibraryItem = null;
        updateApplyButton(defaultApplyLabel, false);
        clearLibrarySelection();
        if (uploadPreview) {
          uploadPreview.textContent = '';
          uploadPreview.classList.add('d-none');
        }
        if (modalFileInput) {
          modalFileInput.value = '';
        }
      }

      function prepareExistingSelection(item, button) {
        if (!item) return;
        pendingLibraryItem = item;
        pendingFile = null;
        clearLibrarySelection();
        if (button) {
          button.classList.add('active');
          button.setAttribute('aria-pressed', 'true');
        }
        if (uploadPreview) {
          uploadPreview.textContent = '';
          uploadPreview.classList.add('d-none');
        }
        updateApplyButton('Vybrat z knihovny', true);
      }

      function prepareFileSelection(file) {
        if (!file) return;
        pendingFile = file;
        pendingLibraryItem = null;
        clearLibrarySelection();
        if (modalFileInput) {
          try { modalFileInput.value = ''; } catch (err) {}
        }
        if (uploadPreview) {
          var summary = file.name || 'Soubor';
          if (file.type) {
            summary += ' (' + file.type + ')';
          }
          uploadPreview.textContent = summary;
          uploadPreview.classList.remove('d-none');
        }
        updateApplyButton('Použít nahraný soubor', true);
      }

      function setRemoveEnabled(enable) {
        if (!removeBtn) return;
        removeBtn.disabled = !enable;
        if (enable) {
          removeBtn.classList.remove('disabled');
        } else {
          removeBtn.classList.add('disabled');
        }
      }

      function applyFile(file) {
        if (!fileInput || !file) return;
        if (uploadInfo) {
          uploadInfo.textContent = file.name || 'Soubor';
          uploadInfo.classList.remove('d-none');
        }
        fileInput.files = (function () {
          var dataTransfer = new DataTransfer();
          dataTransfer.items.add(file);
          return dataTransfer.files;
        })();
        if (removeFlagInput) {
          removeFlagInput.value = '0';
        }
        if (selectedInput) {
          selectedInput.value = '';
        }
        setRemoveEnabled(true);
        if (previewInner) {
          previewInner.innerHTML = '<div class="text-secondary">Soubor bude nahrán po uložení.</div>';
        }
        if (modalEl && typeof bootstrap !== 'undefined') {
          bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        }
      }

      function selectExisting(item) {
        if (!item) return;
        if (uploadInfo) {
          uploadInfo.textContent = '';
          uploadInfo.classList.add('d-none');
        }
        if (removeFlagInput) {
          removeFlagInput.value = '0';
        }
        if (selectedInput) {
          selectedInput.value = item.id || '';
        }
        if (fileInput) {
          try { fileInput.value = ''; } catch (err) {}
        }
        setRemoveEnabled(true);
        if (previewInner) {
          if (item.mime && item.mime.indexOf('image/') === 0 && item.url) {
            previewInner.innerHTML = '<img src="' + item.url + '" alt="Vybraný obrázek" style="max-width:220px;border-radius:.75rem">';
          } else {
            var html = '<div><div class="fw-semibold text-truncate" style="max-width:260px;">' + (item.url || 'Soubor') + '</div>';
            if (item.mime) {
              html += '<div class="text-secondary small mt-1">' + item.mime + '</div>';
            }
            html += '</div>';
            previewInner.innerHTML = html;
          }
        }
        if (modalEl && typeof bootstrap !== 'undefined') {
          bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        }
      }

      function commitPendingSelection() {
        if (pendingFile) {
          applyFile(pendingFile);
          resetPendingSelection();
        } else if (pendingLibraryItem) {
          selectExisting(pendingLibraryItem);
          resetPendingSelection();
        }
      }

      if (removeBtn) {
        removeBtn.addEventListener('click', function (evt) {
          evt.preventDefault();
          if (removeFlagInput) {
            removeFlagInput.value = '1';
          }
          if (selectedInput) {
            selectedInput.value = '';
          }
          if (fileInput) {
            try { fileInput.value = ''; } catch (err) {}
          }
          if (previewInner) {
            previewInner.innerHTML = '<div class="text-secondary">Žádný obrázek není vybrán.</div>';
          }
          setRemoveEnabled(false);
        });
      }

      if (fileInput) {
        fileInput.addEventListener('change', function () {
          if (fileInput.files && fileInput.files[0]) {
            applyFile(fileInput.files[0]);
          }
        });
      }

      if (modalFileInput) {
        modalFileInput.addEventListener('change', function () {
          if (modalFileInput.files && modalFileInput.files[0]) {
            prepareFileSelection(modalFileInput.files[0]);
          }
        });
      }

      if (dropzone) {
        ['dragover', 'dragenter'].forEach(function (eventName) {
          dropzone.addEventListener(eventName, function (evt) {
            evt.preventDefault();
            dropzone.classList.add('border-primary');
          });
        });
        ['dragleave', 'drop'].forEach(function (eventName) {
          dropzone.addEventListener(eventName, function (evt) {
            evt.preventDefault();
            dropzone.classList.remove('border-primary');
          });
        });
        dropzone.addEventListener('drop', function (evt) {
          if (evt.dataTransfer && evt.dataTransfer.files && evt.dataTransfer.files[0]) {
            prepareFileSelection(evt.dataTransfer.files[0]);
          }
        });
        dropzone.addEventListener('click', function (evt) {
          if (!modalFileInput) { return; }
          if (evt.target === modalFileInput) { return; }
          evt.preventDefault();
          modalFileInput.click();
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
          btn.addEventListener('click', function (evt) {
            evt.preventDefault();
            prepareExistingSelection(item, btn);
          });
          btn.addEventListener('dblclick', function (evt) {
            evt.preventDefault();
            prepareExistingSelection(item, btn);
            commitPendingSelection();
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

      if (applyBtn) {
        applyBtn.addEventListener('click', function (evt) {
          evt.preventDefault();
          commitPendingSelection();
        });
      }

      if (modalEl) {
        modalEl.addEventListener('show.bs.modal', function () {
          resetPendingSelection();
        });
        modalEl.addEventListener('hidden.bs.modal', function () {
          resetPendingSelection();
        });
      }

      var hasInitial = selectedInput && selectedInput.value !== '';
      if (!hasInitial && previewWrapper) {
        var initialData = previewWrapper.getAttribute('data-initial');
        if (initialData && initialData !== 'null') {
          hasInitial = true;
        }
      }
      setRemoveEnabled(hasInitial);
      resetPendingSelection();
    })();
  </script>
</div>
