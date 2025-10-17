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
      <form class="row gy-2 gx-2 align-items-end" method="get" action="admin.php">
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
    <div class="card-body">
      <form method="post" action="admin.php?r=media&a=upload" enctype="multipart/form-data" class="row gy-2 gx-2 align-items-end" data-ajax>
        <div class="col-md-6">
          <label class="form-label" for="media-files">Nahrát soubor(y)</label>
          <input class="form-control" id="media-files" type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.webp,.gif,.svg,.pdf,.zip,.txt,.csv,image/*,application/pdf,application/zip,text/plain,text/csv">
          <div class="form-text">Uloží se do <code>uploads/Y/m/media/</code>.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">&nbsp;</label>
          <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
          <button class="btn btn-success btn-sm w-100" type="submit">
            <i class="bi bi-cloud-upload me-1"></i>Nahrát
          </button>
        </div>
      </form>
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
                      <form method="post" action="admin.php?r=media&a=optimize" onsubmit="return confirm('Vytvořit WebP variantu?');" data-ajax>
                        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                        <input type="hidden" name="id" value="<?= $h((string)$m['id']) ?>">
                        <button class="btn btn-outline-success btn-sm" type="submit">Optimalizovat</button>
                      </form>
                    <?php endif; ?>
                    <form method="post" action="admin.php?r=media&a=delete" onsubmit="return confirm('Opravdu odstranit?');" data-ajax>
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
  (function() {
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

          modal.show();
        });
      });
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initMediaModal);
    } else {
      initMediaModal();
    }
  })();
  </script>
<?php
});
