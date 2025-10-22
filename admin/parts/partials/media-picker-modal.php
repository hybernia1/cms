<?php
declare(strict_types=1);
/**
 * @var string $modalId
 * @var string $title
 * @var array<string,string|bool|null>|null $modalAttributes
 * @var string|null $dialogClass
 * @var array<string,mixed>|null $tabs
 * @var array<string,mixed>|null $upload
 * @var array<string,mixed>|null $library
 * @var array<string,mixed>|null $applyButton
 * @var callable|null $afterTabs
 * @var callable|null $footerBeforeButtons
 * @var callable|null $footer
 * @var string|null $headerCloseLabel
 * @var string|null $footerCloseLabel
 */

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$renderAttributes = static function (?array $attributes) use ($h): string {
    if (empty($attributes)) {
        return '';
    }
    $parts = [];
    foreach ($attributes as $attr => $value) {
        if ($value === null || $value === false) {
            continue;
        }
        $attr = (string)$attr;
        if ($value === true) {
            $parts[] = $h($attr) . '="' . $h($attr) . '"';
        } else {
            $parts[] = $h($attr) . '="' . $h((string)$value) . '"';
        }
    }
    return $parts ? ' ' . implode(' ', $parts) : '';
};
$normalizeId = static function (string $value) use ($h): string {
    $normalized = preg_replace('/[^A-Za-z0-9_\-:]/', '-', $value);
    if ($normalized === null || $normalized === '') {
        $normalized = $value !== '' ? $value : uniqid('picker', true);
    }
    return $normalized;
};

$tabsConfig = is_array($tabs) ? $tabs : [];
$uploadConfig = is_array($upload) ? $upload : [];
$libraryConfig = is_array($library) ? $library : [];
$applyConfig = is_array($applyButton) ? $applyButton : [];

$uploadTabLabel = isset($tabsConfig['upload']) ? (string)$tabsConfig['upload'] : 'Nahrát nový';
$libraryTabLabel = isset($tabsConfig['library']) ? (string)$tabsConfig['library'] : 'Knihovna';

$dropzoneClass = isset($uploadConfig['dropzoneClass'])
    ? (string)$uploadConfig['dropzoneClass']
    : 'border border-dashed rounded-3 p-4 text-center bg-body-tertiary';
$dropzoneAttributes = isset($uploadConfig['dropzoneAttributes']) && is_array($uploadConfig['dropzoneAttributes'])
    ? $uploadConfig['dropzoneAttributes']
    : [];
$dropzoneAttributes['data-media-picker-dropzone'] = '1';
$iconClass = isset($uploadConfig['iconClass']) ? (string)$uploadConfig['iconClass'] : 'bi bi-cloud-arrow-up fs-2 mb-2 d-block';
$headline = isset($uploadConfig['headline']) ? (string)$uploadConfig['headline'] : 'Přetáhni soubor sem nebo klikni pro výběr.';
$headlineClass = isset($uploadConfig['headlineClass']) ? (string)$uploadConfig['headlineClass'] : 'mb-2';
$description = isset($uploadConfig['description']) ? (string)$uploadConfig['description'] : 'Podporované formáty: JPG, PNG, GIF, WEBP, PDF.';
$descriptionClass = isset($uploadConfig['descriptionClass']) ? (string)$uploadConfig['descriptionClass'] : 'text-secondary small mb-3';
$inputConfig = isset($uploadConfig['input']) && is_array($uploadConfig['input']) ? $uploadConfig['input'] : [];
$inputAttributes = $inputConfig['attributes'] ?? [];
if (!is_array($inputAttributes)) {
    $inputAttributes = [];
}
$inputAttributes['type'] = 'file';
$inputAttributes['data-media-picker-file-input'] = '1';
if (isset($inputConfig['accept'])) {
    $inputAttributes['accept'] = (string)$inputConfig['accept'];
}
if (isset($inputConfig['id'])) {
    $inputAttributes['id'] = (string)$inputConfig['id'];
}
if (isset($inputConfig['name'])) {
    $inputAttributes['name'] = (string)$inputConfig['name'];
}
$inputClass = isset($inputConfig['class']) ? (string)$inputConfig['class'] : 'form-control';
$inputStyle = isset($inputConfig['style']) ? (string)$inputConfig['style'] : 'max-width:320px;margin:0 auto;';
$inputAttributes['class'] = trim(($inputAttributes['class'] ?? '') . ' ' . $inputClass);
if ($inputStyle !== '') {
    $inputAttributes['style'] = $inputStyle;
}
$summaryClass = isset($uploadConfig['summaryClass']) ? (string)$uploadConfig['summaryClass'] : 'text-secondary small mt-2 d-none';
$noteText = isset($uploadConfig['note']) ? (string)$uploadConfig['note'] : '';
$noteClass = isset($uploadConfig['noteClass']) ? (string)$uploadConfig['noteClass'] : 'text-secondary small mt-3 mb-0';

$libraryLoadingHtml = isset($libraryConfig['loadingHtml']) ? (string)$libraryConfig['loadingHtml'] : '';
if ($libraryLoadingHtml === '') {
    $libraryLoadingHtml = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Načítání…</span></div>';
}
$libraryErrorClass = isset($libraryConfig['errorClass']) ? (string)$libraryConfig['errorClass'] : 'alert alert-danger d-none';
$libraryEmptyText = isset($libraryConfig['emptyText']) ? (string)$libraryConfig['emptyText'] : 'Žádné položky zatím nejsou k dispozici.';
$libraryEmptyClass = isset($libraryConfig['emptyClass']) ? (string)$libraryConfig['emptyClass'] : 'text-secondary text-center py-4 d-none';
$libraryGridClass = isset($libraryConfig['gridClass']) ? (string)$libraryConfig['gridClass'] : 'row g-3';

$applyDefaultLabel = isset($applyConfig['defaultLabel']) ? (string)$applyConfig['defaultLabel'] : 'Použít';
$applyUploadLabel = isset($applyConfig['uploadLabel']) ? (string)$applyConfig['uploadLabel'] : 'Použít nahraný soubor';
$applyLibraryLabel = isset($applyConfig['libraryLabel']) ? (string)$applyConfig['libraryLabel'] : 'Vybrat z knihovny';
$applyClass = isset($applyConfig['class']) ? (string)$applyConfig['class'] : 'btn btn-primary';
$applyIcon = isset($applyConfig['icon']) ? (string)$applyConfig['icon'] : '';
$applyAttributes = isset($applyConfig['attributes']) && is_array($applyConfig['attributes']) ? $applyConfig['attributes'] : [];
$applyAttributes['type'] = $applyAttributes['type'] ?? 'button';
$applyAttributes['class'] = trim(($applyAttributes['class'] ?? '') . ' ' . $applyClass);
$applyAttributes['data-media-picker-apply'] = $applyAttributes['data-media-picker-apply'] ?? '1';
$applyAttributes['data-default-label'] = $applyDefaultLabel;
$applyAttributes['data-media-picker-upload-label'] = $applyUploadLabel;
$applyAttributes['data-media-picker-library-label'] = $applyLibraryLabel;
if (!isset($applyAttributes['disabled'])) {
    $applyAttributes['disabled'] = 'disabled';
}
if (isset($applyConfig['id'])) {
    $applyAttributes['id'] = (string)$applyConfig['id'];
}

$afterTabsRenderer = isset($afterTabs) && is_callable($afterTabs) ? $afterTabs : null;
$footerBeforeRenderer = isset($footerBeforeButtons) && is_callable($footerBeforeButtons) ? $footerBeforeButtons : null;
$footerRenderer = isset($footer) && is_callable($footer) ? $footer : null;

$dialogClass = isset($dialogClass) && $dialogClass !== '' ? $dialogClass : 'modal-xl modal-dialog-scrollable';
$headerCloseLabel = isset($headerCloseLabel) && $headerCloseLabel !== '' ? $headerCloseLabel : 'Zavřít';
$footerCloseLabel = isset($footerCloseLabel) && $footerCloseLabel !== '' ? $footerCloseLabel : 'Zavřít';
$modalAttributes = is_array($modalAttributes) ? $modalAttributes : [];
$modalAttributes['data-media-picker-modal'] = '1';
if (!array_key_exists('aria-hidden', $modalAttributes)) {
    $modalAttributes['aria-hidden'] = 'true';
}

$uploadTabId = $normalizeId($modalId . '-upload-tab');
$uploadPaneId = $normalizeId($modalId . '-upload-pane');
$libraryTabId = $normalizeId($modalId . '-library-tab');
$libraryPaneId = $normalizeId($modalId . '-library-pane');

$bodyRenderer = static function () use (
    $renderAttributes,
    $h,
    $dropzoneClass,
    $dropzoneAttributes,
    $iconClass,
    $headline,
    $headlineClass,
    $description,
    $descriptionClass,
    $inputAttributes,
    $noteText,
    $noteClass,
    $summaryClass,
    $libraryLoadingHtml,
    $libraryErrorClass,
    $libraryEmptyText,
    $libraryEmptyClass,
    $libraryGridClass,
    $uploadTabId,
    $uploadPaneId,
    $libraryTabId,
    $libraryPaneId,
    $uploadTabLabel,
    $libraryTabLabel,
    $afterTabsRenderer
): void {
    ?>
    <ul class="nav nav-tabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button
          class="nav-link active"
          id="<?= $h($uploadTabId) ?>"
          data-bs-toggle="tab"
          data-bs-target="#<?= $h($uploadPaneId) ?>"
          type="button"
          role="tab"
          aria-controls="<?= $h($uploadPaneId) ?>"
          aria-selected="true"
          data-media-picker-upload-tab
        ><?= $h($uploadTabLabel) ?></button>
      </li>
      <li class="nav-item" role="presentation">
        <button
          class="nav-link"
          id="<?= $h($libraryTabId) ?>"
          data-bs-toggle="tab"
          data-bs-target="#<?= $h($libraryPaneId) ?>"
          type="button"
          role="tab"
          aria-controls="<?= $h($libraryPaneId) ?>"
          aria-selected="false"
          data-media-picker-library-tab
        ><?= $h($libraryTabLabel) ?></button>
      </li>
    </ul>
    <div class="tab-content pt-3">
      <div
        class="tab-pane fade show active"
        id="<?= $h($uploadPaneId) ?>"
        role="tabpanel"
        aria-labelledby="<?= $h($uploadTabId) ?>"
        tabindex="0"
      >
        <div class="<?= $h($dropzoneClass) ?>"<?= $renderAttributes($dropzoneAttributes) ?>>
          <?php if ($iconClass !== ''): ?>
            <i class="<?= $h($iconClass) ?>"></i>
          <?php endif; ?>
          <?php if ($headline !== ''): ?>
            <p class="<?= $h($headlineClass) ?>"><?= $h($headline) ?></p>
          <?php endif; ?>
          <?php if ($description !== ''): ?>
            <p class="<?= $h($descriptionClass) ?>"><?= $h($description) ?></p>
          <?php endif; ?>
          <input<?= $renderAttributes($inputAttributes) ?>>
          <?php if ($noteText !== ''): ?>
            <p class="<?= $h($noteClass) ?>" data-media-picker-upload-note><?= $h($noteText) ?></p>
          <?php endif; ?>
          <div class="<?= $h($summaryClass) ?>" data-media-picker-upload-summary></div>
        </div>
      </div>
      <div
        class="tab-pane fade"
        id="<?= $h($libraryPaneId) ?>"
        role="tabpanel"
        aria-labelledby="<?= $h($libraryTabId) ?>"
        tabindex="0"
      >
        <div class="text-center py-4 d-none" data-media-picker-library-loading><?= $libraryLoadingHtml ?></div>
        <div class="<?= $h($libraryErrorClass) ?>" data-media-picker-library-error></div>
        <div class="<?= $h($libraryEmptyClass) ?>" data-media-picker-library-empty><?= $h($libraryEmptyText) ?></div>
        <div class="<?= $h($libraryGridClass) ?>" data-media-picker-library-grid></div>
      </div>
    </div>
    <?php if ($afterTabsRenderer !== null) { $afterTabsRenderer(); } ?>
    <?php
};

if ($footerRenderer === null) {
    $footerRenderer = static function () use ($renderAttributes, $h, $applyAttributes, $applyIcon, $applyDefaultLabel, $footerBeforeRenderer, $footerCloseLabel): void {
        if ($footerBeforeRenderer !== null) {
            $footerBeforeRenderer();
        }
        ?>
        <button<?= $renderAttributes($applyAttributes) ?>>
          <?php if ($applyIcon !== ''): ?>
            <i class="<?= $h($applyIcon) ?>"></i>
          <?php endif; ?>
          <span data-label><?= $h($applyDefaultLabel) ?></span>
        </button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= $h($footerCloseLabel) ?></button>
        <?php
    };
}

$this->render('parts/partials/media-upload-modal', [
    'modalId'          => $modalId,
    'title'            => $title,
    'dialogClass'      => $dialogClass,
    'modalAttributes'  => $modalAttributes,
    'headerCloseLabel' => $headerCloseLabel,
    'footerCloseLabel' => $footerCloseLabel,
    'body'             => $bodyRenderer,
    'footer'           => $footerRenderer,
]);
