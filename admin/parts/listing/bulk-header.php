<?php
declare(strict_types=1);

/**
 * @var string $formId
 * @var string $actionSelectId
 * @var string $applyButtonId
 * @var array<int,array{value:string,label:string}> $options
 * @var string $counterId
 * @var string|null $placeholder
 * @var string|null $applyLabel
 * @var string|null $applyIcon
 * @var string|null $applyClass
 * @var string|null $wrapperClasses
 * @var string|null $controlsWrapper
 */

$placeholder    = $placeholder ?? 'Hromadná akce…';
$applyLabel     = $applyLabel ?? 'Provést';
$applyIcon      = $applyIcon ?? '';
$applyClass     = $applyClass ?? 'btn btn-primary btn-sm';
$wrapperClasses = $wrapperClasses ?? 'card-header d-flex flex-column flex-md-row gap-2 align-items-start align-items-md-center';
$controlsWrapper = $controlsWrapper ?? 'd-flex flex-wrap align-items-center gap-2';

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div class="<?= $h($wrapperClasses) ?>">
  <div class="<?= $h($controlsWrapper) ?>">
    <select class="form-select form-select-sm" name="bulk_action" id="<?= $h($actionSelectId) ?>" form="<?= $h($formId) ?>">
      <option value=""><?= $h($placeholder) ?></option>
      <?php foreach ($options as $option): ?>
        <option value="<?= $h((string)$option['value']) ?>"><?= $h((string)$option['label']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="<?= $h($applyClass) ?>" type="submit" id="<?= $h($applyButtonId) ?>" form="<?= $h($formId) ?>" disabled>
      <?php if ($applyIcon !== ''): ?>
        <i class="<?= $h($applyIcon) ?> me-1"></i>
      <?php endif; ?>
      <?= $h($applyLabel) ?>
    </button>
  </div>
  <div class="ms-md-auto small text-secondary" id="<?= $h($counterId) ?>" aria-live="polite"></div>
</div>
