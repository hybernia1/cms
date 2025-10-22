<?php
declare(strict_types=1);
/**
 * @var string $modalId
 * @var string $title
 * @var array<string,string|bool|null>|null $modalAttributes
 * @var string|null $dialogClass
 * @var array<string,mixed>|null $form
 * @var array<string,string|bool|null>|null $contentAttributes
 * @var array<string,mixed>|null $dropzone
 * @var callable|null $body
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

$ensureAttribute = static function (array &$attributes, string $attribute, string $value): void {
    if (!array_key_exists($attribute, $attributes)) {
        $attributes[$attribute] = $value;
    }
};

$dialogClass = isset($dialogClass) && $dialogClass !== '' ? $dialogClass : 'modal-lg modal-dialog-centered';
$headerCloseLabel = isset($headerCloseLabel) && $headerCloseLabel !== '' ? $headerCloseLabel : 'Zavřít';
$footerCloseLabel = isset($footerCloseLabel) && $footerCloseLabel !== '' ? $footerCloseLabel : 'Zavřít';
$modalAttributes = $modalAttributes ?? [];
if (!array_key_exists('aria-hidden', $modalAttributes)) {
    $modalAttributes['aria-hidden'] = 'true';
}
$formConfig = isset($form) && is_array($form) ? $form : null;
$dropzoneConfig = isset($dropzone) && is_array($dropzone) ? $dropzone : null;
$bodyRenderer = isset($body) && is_callable($body) ? $body : null;
$footerRenderer = isset($footer) && is_callable($footer) ? $footer : null;

$shouldInitUpload = $formConfig !== null;
if ($shouldInitUpload) {
    $ensureAttribute($modalAttributes, 'data-media-upload-modal', '1');
}
$modalAttrString = $renderAttributes($modalAttributes);
$contentAttributes = $contentAttributes ?? [];

$formMethod = 'post';
$formAction = '';
$formId = '';
$formEnctype = '';
$formAttributes = [];
$formHiddenFields = [];
$submitButton = null;
if ($formConfig !== null) {
    $formMethod = isset($formConfig['method']) ? (string)$formConfig['method'] : 'post';
    $formAction = isset($formConfig['action']) ? (string)$formConfig['action'] : '';
    $formId = isset($formConfig['id']) ? (string)$formConfig['id'] : '';
    $formEnctype = isset($formConfig['enctype']) ? (string)$formConfig['enctype'] : '';
    $formAttributes = isset($formConfig['attributes']) && is_array($formConfig['attributes']) ? $formConfig['attributes'] : [];
    $ensureAttribute($formAttributes, 'data-role', 'media-upload-form');
    $formHiddenFields = isset($formConfig['hiddenFields']) && is_array($formConfig['hiddenFields']) ? $formConfig['hiddenFields'] : [];
    $submitButton = isset($formConfig['submitButton']) && is_array($formConfig['submitButton']) ? $formConfig['submitButton'] : null;
}

$renderButton = static function (array $config) use ($h, $renderAttributes): void {
    $label = (string)($config['label'] ?? '');
    $class = (string)($config['class'] ?? 'btn btn-primary');
    $type = (string)($config['type'] ?? 'button');
    $icon = isset($config['icon']) ? (string)$config['icon'] : '';
    $id = isset($config['id']) ? (string)$config['id'] : '';
    $disabled = !empty($config['disabled']);
    $attributes = isset($config['attributes']) && is_array($config['attributes']) ? $config['attributes'] : [];
    if ($id !== '') {
        $attributes['id'] = $id;
    }
    if ($disabled) {
        $attributes['disabled'] = 'disabled';
    }
    if (!isset($attributes['type'])) {
        $attributes['type'] = $type;
    }
    $attributes['class'] = trim(($attributes['class'] ?? '') . ' ' . $class);
    echo '<button' . $renderAttributes($attributes) . '>';
    if ($icon !== '') {
        echo '<i class="' . $h($icon) . '"></i>';
    }
    echo $h($label);
    echo '</button>';
};
?>
<div class="modal fade" id="<?= $h($modalId) ?>" tabindex="-1"<?= $modalAttrString ?>>
  <div class="modal-dialog <?= $h($dialogClass) ?>">
    <?php if ($formConfig !== null): ?>
      <form
        class="modal-content"
        method="<?= $h($formMethod) ?>"
        action="<?= $h($formAction) ?>"
        <?php if ($formId !== ''): ?>id="<?= $h($formId) ?>"<?php endif; ?>
        <?php if ($formEnctype !== ''): ?>enctype="<?= $h($formEnctype) ?>"<?php endif; ?>
        <?= $renderAttributes($formAttributes) ?>
      >
    <?php else: ?>
      <div class="modal-content"<?= $renderAttributes($contentAttributes) ?>>
    <?php endif; ?>
        <div class="modal-header">
          <h5 class="modal-title"><?= $h($title) ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $h($headerCloseLabel) ?>"></button>
        </div>
        <div class="modal-body">
          <?php if ($dropzoneConfig !== null):
            $dropzoneId = isset($dropzoneConfig['id']) ? (string)$dropzoneConfig['id'] : '';
            $dropzoneClass = isset($dropzoneConfig['class']) ? (string)$dropzoneConfig['class'] : 'admin-dropzone';
            $dropzoneAttributes = isset($dropzoneConfig['attributes']) && is_array($dropzoneConfig['attributes']) ? $dropzoneConfig['attributes'] : [];
            if ($dropzoneId !== '') {
                $dropzoneAttributes['id'] = $dropzoneId;
            }
            $ensureAttribute($dropzoneAttributes, 'data-role', 'dropzone');
            $iconClass = isset($dropzoneConfig['iconClass']) ? (string)$dropzoneConfig['iconClass'] : 'bi bi-cloud-arrow-up fs-2 mb-2 d-block';
            $headline = isset($dropzoneConfig['headline']) ? (string)$dropzoneConfig['headline'] : '';
            $headlineClass = isset($dropzoneConfig['headlineClass']) ? (string)$dropzoneConfig['headlineClass'] : 'mb-1';
            $description = isset($dropzoneConfig['description']) ? (string)$dropzoneConfig['description'] : '';
            $descriptionClass = isset($dropzoneConfig['descriptionClass']) ? (string)$dropzoneConfig['descriptionClass'] : 'text-secondary small mb-3';
            $browseButton = isset($dropzoneConfig['browseButton']) && is_array($dropzoneConfig['browseButton']) ? $dropzoneConfig['browseButton'] : null;
            $summary = isset($dropzoneConfig['summary']) && is_array($dropzoneConfig['summary']) ? $dropzoneConfig['summary'] : null;
            $fileInput = isset($dropzoneConfig['fileInput']) && is_array($dropzoneConfig['fileInput']) ? $dropzoneConfig['fileInput'] : null;
            $extraInputs = isset($dropzoneConfig['extraInputs']) && is_array($dropzoneConfig['extraInputs']) ? $dropzoneConfig['extraInputs'] : [];
          ?>
            <div class="<?= $h($dropzoneClass) ?>"<?= $renderAttributes($dropzoneAttributes) ?>>
              <?php if ($iconClass !== ''): ?><i class="<?= $h($iconClass) ?>"></i><?php endif; ?>
              <?php if ($headline !== ''): ?><p class="<?= $h($headlineClass) ?>"><?= $h($headline) ?></p><?php endif; ?>
              <?php if ($description !== ''): ?><p class="<?= $h($descriptionClass) ?>"><?= $h($description) ?></p><?php endif; ?>
              <?php if ($browseButton !== null):
                $browseAttributes = isset($browseButton['attributes']) && is_array($browseButton['attributes']) ? $browseButton['attributes'] : [];
                $browseAttributes['type'] = $browseAttributes['type'] ?? 'button';
                $browseAttributes['class'] = trim(($browseAttributes['class'] ?? '') . ' ' . (string)($browseButton['class'] ?? 'btn btn-outline-secondary btn-sm'));
                if (isset($browseButton['id'])) {
                    $browseAttributes['id'] = (string)$browseButton['id'];
                }
                $ensureAttribute($browseAttributes, 'data-role', 'browse-button');
              ?>
                <button<?= $renderAttributes($browseAttributes) ?>><?= $h((string)($browseButton['label'] ?? 'Vybrat soubory')) ?></button>
              <?php endif; ?>
            </div>
            <?php if ($summary !== null):
              $summaryAttributes = isset($summary['attributes']) && is_array($summary['attributes']) ? $summary['attributes'] : [];
              if (isset($summary['id'])) {
                  $summaryAttributes['id'] = (string)$summary['id'];
              }
              $summaryClass = isset($summary['class']) ? (string)$summary['class'] : 'text-secondary small mt-3 d-none';
              $summaryAttributes['class'] = trim(($summaryAttributes['class'] ?? '') . ' ' . $summaryClass);
              $ensureAttribute($summaryAttributes, 'data-role', 'summary');
            ?>
              <div<?= $renderAttributes($summaryAttributes) ?>></div>
            <?php endif; ?>
            <?php if ($fileInput !== null):
              $fileAttributes = isset($fileInput['attributes']) && is_array($fileInput['attributes']) ? $fileInput['attributes'] : [];
              $fileAttributes['type'] = $fileAttributes['type'] ?? 'file';
              if (!empty($fileInput['multiple'])) {
                  $fileAttributes['multiple'] = 'multiple';
              }
              if (isset($fileInput['id'])) {
                  $fileAttributes['id'] = (string)$fileInput['id'];
              }
              if (isset($fileInput['name'])) {
                  $fileAttributes['name'] = (string)$fileInput['name'];
              }
              if (isset($fileInput['accept'])) {
                  $fileAttributes['accept'] = (string)$fileInput['accept'];
              }
              $fileClass = isset($fileInput['class']) ? (string)$fileInput['class'] : '';
              if ($fileClass !== '') {
                  $fileAttributes['class'] = trim(($fileAttributes['class'] ?? '') . ' ' . $fileClass);
              }
              $ensureAttribute($fileAttributes, 'data-role', 'file-input');
              $fileValue = isset($fileInput['value']) ? (string)$fileInput['value'] : '';
            ?>
              <input<?= $renderAttributes($fileAttributes) ?> value="<?= $h($fileValue) ?>">
            <?php endif; ?>
            <?php foreach ($extraInputs as $extraInput):
              if (!is_array($extraInput)) {
                  continue;
              }
              $extraType = isset($extraInput['type']) ? (string)$extraInput['type'] : 'hidden';
              $extraName = isset($extraInput['name']) ? (string)$extraInput['name'] : '';
              if ($extraName === '') {
                  continue;
              }
              $extraValue = isset($extraInput['value']) ? (string)$extraInput['value'] : '';
              $extraAttributes = isset($extraInput['attributes']) && is_array($extraInput['attributes']) ? $extraInput['attributes'] : [];
            ?>
              <input type="<?= $h($extraType) ?>" name="<?= $h($extraName) ?>" value="<?= $h($extraValue) ?>"<?= $renderAttributes($extraAttributes) ?>>
            <?php endforeach; ?>
          <?php endif; ?>
          <?php if ($bodyRenderer !== null): ?>
            <?php $bodyRenderer(); ?>
          <?php endif; ?>
          <?php if ($formHiddenFields !== []): ?>
            <?php foreach ($formHiddenFields as $field):
              if (!is_array($field)) {
                  continue;
              }
              $fieldType = isset($field['type']) ? (string)$field['type'] : 'hidden';
              $fieldName = isset($field['name']) ? (string)$field['name'] : '';
              if ($fieldName === '') {
                  continue;
              }
              $fieldValue = isset($field['value']) ? (string)$field['value'] : '';
              $fieldAttributes = isset($field['attributes']) && is_array($field['attributes']) ? $field['attributes'] : [];
            ?>
              <input type="<?= $h($fieldType) ?>" name="<?= $h($fieldName) ?>" value="<?= $h($fieldValue) ?>"<?= $renderAttributes($fieldAttributes) ?>>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <?php if ($footerRenderer !== null): ?>
            <?php $footerRenderer(); ?>
          <?php else: ?>
            <?php if ($submitButton !== null):
              if (!isset($submitButton['attributes']) || !is_array($submitButton['attributes'])) {
                  $submitButton['attributes'] = [];
              }
              $ensureAttribute($submitButton['attributes'], 'data-role', 'submit');
              $submitButton['type'] = $submitButton['type'] ?? 'submit';
              $submitButton['class'] = $submitButton['class'] ?? 'btn btn-primary';
              $renderButton($submitButton);
            endif; ?>
            <?php
              $closeButton = [
                'label' => $footerCloseLabel,
                'class' => 'btn btn-outline-secondary',
                'type'  => 'button',
                'attributes' => [
                  'data-bs-dismiss' => 'modal',
                ],
              ];
              $renderButton($closeButton);
            ?>
          <?php endif; ?>
        </div>
    <?php if ($formConfig !== null): ?>
      </form>
    <?php else: ?>
      </div>
    <?php endif; ?>
  </div>
</div>
