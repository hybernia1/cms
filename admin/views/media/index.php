<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array $filters */
/** @var array<int,array{
 *   id:int,
 *   user_id:int,
 *   type:string,
 *   mime:string,
 *   url:string,
 *   rel_path:string,
 *   created_at:string,
 *   created_display:string,
 *   created_iso:string,
 *   author_name:string,
 *   author_email:string,
 *   meta:array<string,mixed>,
 *   webp_url:?string,
 *   display_url:string,
 *   has_webp:bool,
 *   size_bytes:?int,
 *   size_human:?string
 * }> $items */
/** @var array{page:int,per_page:int,total:int,pages:int} $pagination */
/** @var string $csrf */
/** @var bool $webpEnabled */

$this->render('layouts/base', compact('pageTitle','nav','currentUser','flash'), function () use ($filters,$items,$pagination,$csrf,$webpEnabled) {
  $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $jsonAttr = static function(array $data) use ($h): string {
    $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
      return $h('{}');
    }
    return $h($encoded);
  };
?>
  <div class="card mb-3">
    <div class="card-body">
      <form class="row gy-2 gx-2 align-items-end" method="get" action="admin.php" data-ajax>
        <input type="hidden" name="r" value="media">
        <div class="col-md-3">
          <label class="form-label" for="media-type">Typ</label>
          <select class="form-select form-select-sm" name="type" id="media-type">
            <option value="">Všechny</option>
            <?php foreach (['image'=>'Obrázky','file'=>'Soubory'] as $value=>$label): ?>
              <option value="<?= $h($value) ?>" <?= ($filters['type'] ?? '')===$value?'selected':'' ?>><?= $h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="media-search">Hledat (URL/MIME)</label>
          <div class="input-group input-group-sm">
            <input class="form-control" id="media-search" name="q" value="<?= $h((string)($filters['q'] ?? '')) ?>" placeholder="např. .jpg nebo image/">
            <button class="btn btn-outline-secondary" type="submit" aria-label="Hledat" data-bs-toggle="tooltip" data-bs-title="Hledat">
              <i class="bi bi-search"></i>
            </button>
            <a class="btn btn-outline-secondary <?= ($filters['q'] ?? '') === '' && ($filters['type'] ?? '') === '' ? 'disabled' : '' ?>" href="admin.php?r=media" aria-label="Zrušit filtr" data-bs-toggle="tooltip" data-bs-title="Zrušit filtr">
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
    <div class="card-body">
      <?php if (!$items): ?>
        <div class="text-secondary"><i class="bi bi-inbox me-1"></i>Žádná média.</div>
      <?php else: ?>
        <div class="row g-3">
          <?php foreach ($items as $m):
            $isImg = str_starts_with((string)$m['mime'], 'image/');
            $meta = is_array($m['meta'] ?? null) ? $m['meta'] : [];
            $references = [
              'thumbnails' => array_values(array_map(static function (array $ref): array {
                return [
                  'id' => (int)($ref['id'] ?? 0),
                  'title' => (string)($ref['title'] ?? ''),
                  'status' => (string)($ref['status'] ?? ''),
                  'statusLabel' => (string)($ref['status_label'] ?? ''),
                  'type' => (string)($ref['type'] ?? ''),
                  'typeLabel' => (string)($ref['type_label'] ?? ''),
                  'editUrl' => (string)($ref['edit_url'] ?? ''),
                ];
              }, $m['references']['thumbnails'] ?? [])),
              'content' => array_values(array_map(static function (array $ref): array {
                return [
                  'id' => (int)($ref['id'] ?? 0),
                  'title' => (string)($ref['title'] ?? ''),
                  'status' => (string)($ref['status'] ?? ''),
                  'statusLabel' => (string)($ref['status_label'] ?? ''),
                  'type' => (string)($ref['type'] ?? ''),
                  'typeLabel' => (string)($ref['type_label'] ?? ''),
                  'role' => (string)($ref['role'] ?? ''),
                  'roleLabel' => (string)($ref['role_label'] ?? ''),
                  'editUrl' => (string)($ref['edit_url'] ?? ''),
                ];
              }, $m['references']['content'] ?? [])),
            ];
            $modalData = [
              'id'          => $m['id'] ?? null,
              'type'        => $m['type'] ?? '',
              'typeLabel'   => ($m['type'] ?? '') === 'image' ? 'Obrázek' : 'Soubor',
              'mime'        => $m['mime'] ?? '',
              'url'         => $m['url'] ?? '',
              'displayUrl'  => $m['display_url'] ?? ($m['url'] ?? ''),
              'webpUrl'     => $m['webp_url'] ?? null,
              'hasWebp'     => $m['has_webp'] ?? false,
              'width'       => $meta['w'] ?? null,
              'height'      => $meta['h'] ?? null,
              'sizeHuman'   => $m['size_human'] ?? null,
              'sizeBytes'   => $m['size_bytes'] ?? null,
              'created'     => $m['created_display'] ?? '',
              'createdIso'  => $m['created_iso'] ?? '',
              'authorName'  => $m['author_name'] ?? '',
              'authorEmail' => $m['author_email'] ?? '',
              'references'  => $references,
            ];
          ?>
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
              <div class="card h-100 shadow-sm">
                <div class="card-body">
                  <div class="mb-2 d-flex justify-content-center align-items-center bg-body-tertiary rounded" style="min-height:140px; overflow:hidden;">
                    <?php if ($isImg): ?>
                      <button type="button" class="btn p-0 border-0 bg-transparent media-thumb" data-media="<?= $jsonAttr($modalData) ?>" style="max-height:140px;max-width:100%;">
                        <img src="<?= $h((string)($m['display_url'] ?? $m['url'])) ?>" alt="media" style="max-height:140px;max-width:100%;object-fit:contain;">
                      </button>
                    <?php else: ?>
                      <div class="text-center text-secondary small">
                        <div class="mb-2">Soubor</div>
                        <div><code><?= $h((string)$m['mime']) ?></code></div>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div class="small text-secondary mb-2 text-truncate"><i class="bi bi-tag me-1"></i><?= $h((string)$m['mime']) ?></div>
                  <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-light btn-sm border" target="_blank" href="<?= $h((string)$m['url']) ?>">Otevřít</a>
                    <button class="btn btn-light btn-sm border" type="button" onclick="navigator.clipboard.writeText('<?= $h((string)$m['url']) ?>').then(()=>this.textContent='Zkopírováno');">Kopírovat URL</button>
                  </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                  <span class="small text-secondary">#<?= $h((string)$m['id']) ?> • <?= $h($m['created_display'] !== '' ? $m['created_display'] : date('Y-m-d', strtotime((string)$m['created_at']))) ?></span>
                  <div class="d-flex gap-2">
                    <?php if (!empty($webpEnabled) && $isImg && empty($m['has_webp'])): ?>
                      <form method="post" action="admin.php?r=media&a=optimize" onsubmit="return confirm('Vytvořit WebP variantu?');" data-ajax data-action="media_optimize">
                        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                        <input type="hidden" name="id" value="<?= $h((string)$m['id']) ?>">
                        <button class="btn btn-outline-success btn-sm" type="submit">Optimalizovat</button>
                      </form>
                    <?php endif; ?>
                    <form method="post"
                          action="admin.php?r=media&a=delete"
                          data-ajax
                          data-action="media_delete"
                          data-confirm-modal="Opravdu odstranit?"
                          data-confirm-modal-title="Potvrzení smazání"
                          data-confirm-modal-confirm-label="Smazat"
                          data-confirm-modal-cancel-label="Zrušit">
                      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                      <input type="hidden" name="id" value="<?= $h((string)$m['id']) ?>">
                      <button class="btn btn-light btn-sm border" type="submit">Smazat</button>
                    </form>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if (($pagination['pages'] ?? 1) > 1): ?>
          <nav class="mt-3" aria-label="Stránkování">
            <ul class="pagination pagination-sm mb-0">
              <?php
                $page = (int)($pagination['page'] ?? 1);
                $pages = (int)($pagination['pages'] ?? 1);
                $qs = $_GET; unset($qs['page']);
                $base = 'admin.php?'.http_build_query(array_merge(['r'=>'media'], $qs));
              ?>
              <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="<?= $h($base.'&page='.max(1,$page-1)) ?>" aria-label="Předchozí">‹</a></li>
              <?php for($i=max(1,$page-2); $i<=min($pages,$page+2); $i++): ?>
                <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="<?= $h($base.'&page='.$i) ?>"><?= $i ?></a></li>
              <?php endfor; ?>
              <li class="page-item <?= $page>=$pages?'disabled':'' ?>"><a class="page-link" href="<?= $h($base.'&page='.min($pages,$page+1)) ?>" aria-label="Další">›</a></li>
            </ul>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="modal fade" id="mediaUploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <form class="modal-content" method="post" action="admin.php?r=media&a=upload" enctype="multipart/form-data" id="media-upload-form" data-ajax data-action="media_upload">
        <div class="modal-header">
          <h5 class="modal-title">Nahrát soubory</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zavřít"></button>
        </div>
        <div class="modal-body">
          <div class="admin-dropzone" id="media-upload-dropzone">
            <i class="bi bi-cloud-arrow-up fs-2 mb-2 d-block"></i>
            <p class="mb-1">Přetáhni soubory sem nebo klikni pro výběr.</p>
            <p class="text-secondary small mb-3">Podporované formáty: JPG, PNG, GIF, WEBP, SVG, PDF, ZIP, TXT, CSV.</p>
            <button class="btn btn-outline-secondary btn-sm" type="button" id="media-upload-browse">Vybrat soubory</button>
          </div>
          <div id="media-upload-summary" class="text-secondary small mt-3 d-none"></div>
          <input class="d-none" type="file" id="media-upload-input" name="files[]" multiple accept=".jpg,.jpeg,.png,.webp,.gif,.svg,.pdf,.zip,.txt,.csv,image/*,application/pdf,application/zip,text/plain,text/csv">
          <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary" type="submit" id="media-upload-submit" disabled>
            <i class="bi bi-cloud-arrow-up me-1"></i>Nahrát
          </button>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zavřít</button>
        </div>
      </form>
    </div>
  </div>

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

  <script>
  (function () {
    function initMediaModal() {
      const modalEl = document.getElementById('mediaDetailModal');
      if (!modalEl || typeof window.bootstrap === 'undefined' || !window.bootstrap.Modal) {
        return;
      }
      const modal = new window.bootstrap.Modal(modalEl);
      const preview = modalEl.querySelector('[data-role="preview"]');
      const fields = {
        id: modalEl.querySelector('[data-field="id"]'),
        type: modalEl.querySelector('[data-field="type"]'),
        mime: modalEl.querySelector('[data-field="mime"]'),
        dimensions: modalEl.querySelector('[data-field="dimensions"]'),
        size: modalEl.querySelector('[data-field="size"]'),
        created: modalEl.querySelector('[data-field="created"]'),
        author: modalEl.querySelector('[data-field="author"]'),
      };
      const links = {
        original: modalEl.querySelector('[data-link="original"]'),
        webp: modalEl.querySelector('[data-link="webp"]'),
      };
      const usageSection = modalEl.querySelector('[data-role="usage"]');
      const usageField = usageSection ? usageSection.querySelector('[data-field="usage"]') : null;
      const defaultUsageText = 'Médium zatím není připojeno k žádnému příspěvku.';

      const updateField = (key, value) => {
        if (!fields[key]) return;
        fields[key].textContent = value && value !== '' ? value : '—';
      };

      const updateLink = (key, url, hiddenClass) => {
        const el = links[key];
        if (!el) return;
        if (url) {
          el.href = url;
          el.removeAttribute('aria-disabled');
          if (hiddenClass) {
            el.classList.remove(hiddenClass);
          }
        } else {
          el.removeAttribute('href');
          el.setAttribute('aria-disabled', 'true');
          if (hiddenClass) {
            el.classList.add(hiddenClass);
          }
        }
      };

      const renderUsage = (references) => {
        if (!usageField) { return; }
        usageField.innerHTML = '';
        const thumbs = Array.isArray(references && references.thumbnails) ? references.thumbnails : [];
        const content = Array.isArray(references && references.content) ? references.content : [];
        if (!thumbs.length && !content.length) {
          usageField.textContent = defaultUsageText;
          return;
        }

        const appendList = (title, items, includeRole) => {
          if (!items.length) { return; }
          const heading = document.createElement('div');
          heading.className = 'fw-semibold mb-1';
          heading.textContent = title;
          usageField.appendChild(heading);

          const list = document.createElement('ul');
          list.className = 'list-unstyled mb-2';
          items.forEach((item) => {
            const li = document.createElement('li');
            li.className = 'mb-1';
            const titleText = (item && typeof item.title === 'string' && item.title.trim() !== '') ? item.title : 'Bez názvu';
            const prefix = item && item.id ? `#${item.id} – ` : '';
            if (item && item.editUrl) {
              const link = document.createElement('a');
              link.href = item.editUrl;
              link.className = 'text-decoration-none';
              link.textContent = prefix + titleText;
              link.setAttribute('data-no-ajax', 'true');
              li.appendChild(link);
            } else {
              li.textContent = prefix + titleText;
            }
            const metaParts = [];
            if (item && item.typeLabel) metaParts.push(item.typeLabel);
            if (item && item.statusLabel) metaParts.push(item.statusLabel);
            if (includeRole && item && item.roleLabel) metaParts.push(item.roleLabel);
            if (metaParts.length) {
              const meta = document.createElement('div');
              meta.className = 'text-secondary small';
              meta.textContent = metaParts.join(' • ');
              li.appendChild(meta);
            }
            list.appendChild(li);
          });
          usageField.appendChild(list);
        };

        appendList('Náhled příspěvku', thumbs, false);
        appendList('V obsahu', content, true);
        if (usageField.lastElementChild && usageField.lastElementChild.tagName === 'UL') {
          usageField.lastElementChild.classList.remove('mb-2');
          usageField.lastElementChild.classList.add('mb-0');
        }
      };

      document.querySelectorAll('.media-thumb').forEach((btn) => {
        btn.addEventListener('click', () => {
          let data = {};
          try {
            data = JSON.parse(btn.getAttribute('data-media') || '{}');
          } catch (e) {
            data = {};
          }

          if (preview) {
            preview.innerHTML = '';
            if (data.displayUrl) {
              const img = document.createElement('img');
              img.src = data.displayUrl;
              img.alt = 'media detail';
              img.style.maxWidth = '100%';
              img.style.maxHeight = '320px';
              img.style.objectFit = 'contain';
              preview.appendChild(img);
            } else {
              const placeholder = document.createElement('div');
              placeholder.className = 'text-secondary';
              placeholder.textContent = 'Náhled není k dispozici.';
              preview.appendChild(placeholder);
            }
          }

          const dimensions = data.width && data.height ? `${data.width} × ${data.height}px` : '';
          const authorParts = [];
          if (data.authorName) authorParts.push(data.authorName);
          if (data.authorEmail) authorParts.push(`(${data.authorEmail})`);

          updateField('id', data.id ? `#${data.id}` : '');
          updateField('type', data.typeLabel || data.type || '');
          updateField('mime', data.mime || '');
          updateField('dimensions', dimensions);
          updateField('size', data.sizeHuman || (data.sizeBytes ? `${data.sizeBytes} B` : ''));
          updateField('created', data.created || '');
          updateField('author', authorParts.length ? authorParts.join(' ') : '');

          updateLink('original', data.url || '', 'disabled');
          updateLink('webp', data.webpUrl || '', 'd-none');
          renderUsage(data.references || null);

          modal.show();
        });
      });
    }

    function initMediaUploader() {
      const modalEl = document.getElementById('mediaUploadModal');
      const form = document.getElementById('media-upload-form');
      const fileInput = document.getElementById('media-upload-input');
      const dropzone = document.getElementById('media-upload-dropzone');
      const browseBtn = document.getElementById('media-upload-browse');
      const summary = document.getElementById('media-upload-summary');
      const submitBtn = document.getElementById('media-upload-submit');

      if (!modalEl || !form || !fileInput || !summary || !submitBtn) {
        return;
      }

      const formatFileSize = (bytes) => {
        if (!Number.isFinite(bytes) || bytes <= 0) {
          return '';
        }
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        let value = bytes;
        let unitIndex = 0;
        while (value >= 1024 && unitIndex < units.length - 1) {
          value /= 1024;
          unitIndex += 1;
        }
        return unitIndex === 0 ? `${Math.round(value)} ${units[unitIndex]}` : `${value.toFixed(1)} ${units[unitIndex]}`;
      };

      const renderSummary = () => {
        const files = fileInput.files;
        summary.innerHTML = '';
        if (!files || files.length === 0) {
          summary.classList.add('d-none');
          submitBtn.disabled = true;
          return;
        }

        const list = document.createElement('ul');
        list.className = 'list-unstyled mb-2';
        Array.from(files).forEach((file) => {
          const li = document.createElement('li');
          li.className = 'mb-1';
          const sizeText = typeof file.size === 'number' ? formatFileSize(file.size) : '';
          li.textContent = file.name + (sizeText ? ` (${sizeText})` : '');
          list.appendChild(li);
        });
        summary.appendChild(list);
        summary.classList.remove('d-none');
        submitBtn.disabled = false;
      };

      const resetUpload = () => {
        try { fileInput.value = ''; } catch (err) {}
        summary.innerHTML = '';
        summary.classList.add('d-none');
        submitBtn.disabled = true;
      };

      fileInput.addEventListener('change', renderSummary);

      if (browseBtn) {
        browseBtn.addEventListener('click', (evt) => {
          evt.preventDefault();
          fileInput.click();
        });
      }

      if (dropzone) {
        dropzone.addEventListener('dragover', (evt) => {
          evt.preventDefault();
          dropzone.classList.add('is-dragover');
        });
        dropzone.addEventListener('dragleave', () => {
          dropzone.classList.remove('is-dragover');
        });
        dropzone.addEventListener('dragend', () => {
          dropzone.classList.remove('is-dragover');
        });
        dropzone.addEventListener('drop', (evt) => {
          evt.preventDefault();
          dropzone.classList.remove('is-dragover');
          if (!evt.dataTransfer || !evt.dataTransfer.files || evt.dataTransfer.files.length === 0) {
            return;
          }
          const files = evt.dataTransfer.files;
          try {
            const dt = new DataTransfer();
            Array.from(files).forEach((file) => dt.items.add(file));
            fileInput.files = dt.files;
          } catch (err) {
            try { fileInput.files = files; } catch (e) {}
          }
          renderSummary();
        });
        dropzone.addEventListener('click', (evt) => {
          if (browseBtn && browseBtn.contains(evt.target)) {
            return;
          }
          fileInput.click();
        });
      }

      modalEl.addEventListener('show.bs.modal', resetUpload);
      modalEl.addEventListener('hidden.bs.modal', resetUpload);

      resetUpload();
    }

    function onReady(fn) {
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fn);
      } else {
        fn();
      }
    }

    onReady(function () {
      initMediaModal();
      initMediaUploader();
    });
  })();
  </script>
<?php
});
