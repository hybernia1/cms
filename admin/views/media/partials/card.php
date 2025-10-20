<?php
declare(strict_types=1);
/** @var array<string,mixed> $item */
/** @var string $csrf */
/** @var bool $webpEnabled */

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

if (!isset($encodeJsonAttr) || !is_callable($encodeJsonAttr)) {
    $encodeJsonAttr = static function (array $data) use ($h): string {
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $h($encoded === false ? '{}' : $encoded);
    };
}

$mime = (string)($item['mime'] ?? '');
$isImg = str_starts_with($mime, 'image/');
$meta = is_array($item['meta'] ?? null) ? $item['meta'] : [];
$referencesRaw = is_array($item['references'] ?? null) ? $item['references'] : [];
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
    }, $referencesRaw['thumbnails'] ?? [])),
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
    }, $referencesRaw['content'] ?? [])),
];

$modalData = [
    'id'          => $item['id'] ?? null,
    'type'        => $item['type'] ?? '',
    'typeLabel'   => ($item['type'] ?? '') === 'image' ? 'Obrázek' : 'Soubor',
    'mime'        => $mime,
    'url'         => $item['url'] ?? '',
    'displayUrl'  => $item['display_url'] ?? ($item['url'] ?? ''),
    'webpUrl'     => $item['webp_url'] ?? null,
    'hasWebp'     => $item['has_webp'] ?? false,
    'width'       => $meta['w'] ?? null,
    'height'      => $meta['h'] ?? null,
    'sizeHuman'   => $item['size_human'] ?? null,
    'sizeBytes'   => $item['size_bytes'] ?? null,
    'created'     => $item['created_display'] ?? '',
    'createdIso'  => $item['created_iso'] ?? '',
    'authorName'  => $item['author_name'] ?? '',
    'authorEmail' => $item['author_email'] ?? '',
    'references'  => $references,
];

$id = (int)($item['id'] ?? 0);
$createdDisplay = (string)($item['created_display'] ?? '');
$createdRaw = (string)($item['created_at'] ?? '');
?>
<div class="col-12 col-sm-6 col-md-4 col-lg-3" data-media-item data-media-item-id="<?= $h((string)$id) ?>">
  <div class="card h-100 shadow-sm">
    <div class="card-body">
      <div class="mb-2 d-flex justify-content-center align-items-center bg-body-tertiary rounded" style="min-height:140px; overflow:hidden;">
        <?php if ($isImg): ?>
          <button type="button" class="btn p-0 border-0 bg-transparent media-thumb" data-media="<?= $encodeJsonAttr($modalData) ?>" style="max-height:140px;max-width:100%;">
            <img src="<?= $h((string)($item['display_url'] ?? $item['url'])) ?>" alt="media" style="max-height:140px;max-width:100%;object-fit:contain;">
          </button>
        <?php else: ?>
          <div class="text-center text-secondary small">
            <div class="mb-2">Soubor</div>
            <div><code><?= $h($mime) ?></code></div>
          </div>
        <?php endif; ?>
      </div>
      <div class="small text-secondary mb-2 text-truncate"><i class="bi bi-tag me-1"></i><?= $h($mime) ?></div>
      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-light btn-sm border" target="_blank" href="<?= $h((string)($item['url'] ?? '')) ?>">Otevřít</a>
        <button class="btn btn-light btn-sm border" type="button" data-media-copy="<?= $h((string)($item['url'] ?? '')) ?>">Kopírovat URL</button>
      </div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
      <span class="small text-secondary">#<?= $h((string)$id) ?> • <?= $h($createdDisplay !== '' ? $createdDisplay : date('Y-m-d', strtotime($createdRaw))) ?></span>
      <div class="d-flex gap-2">
        <?php if (!empty($webpEnabled) && $isImg && empty($item['has_webp'])): ?>
          <form method="post" action="admin.php?r=media&a=optimize" data-ajax data-media-action="optimize">
            <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
            <input type="hidden" name="id" value="<?= $h((string)$id) ?>">
            <input type="hidden" name="context" data-media-context-input>
            <button class="btn btn-outline-success btn-sm" type="submit">Optimalizovat</button>
          </form>
        <?php endif; ?>
        <form
          method="post"
          action="admin.php?r=media&a=delete"
          data-ajax
          data-media-action="delete"
          data-confirm-modal="Opravdu odstranit?"
          data-confirm-modal-title="Potvrzení smazání"
          data-confirm-modal-confirm-label="Smazat"
          data-confirm-modal-cancel-label="Zrušit"
        >
          <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
          <input type="hidden" name="id" value="<?= $h((string)$id) ?>">
          <input type="hidden" name="context" data-media-context-input>
          <button class="btn btn-light btn-sm border" type="submit">Smazat</button>
        </form>
      </div>
    </div>
  </div>
</div>
