<?php
declare(strict_types=1);

/**
 * @var string $formId
 * @var string $action
 * @var string $csrf
 * @var string $selectAll
 * @var string $rowSelector
 * @var string $actionSelect
 * @var string $applyButton
 * @var string $counter
 * @var array<string,string|int|null>|null $hidden
 * @var string|null $ajaxAction
 */

$hidden = is_array($hidden ?? null) ? $hidden : [];
$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$ajaxAction = isset($ajaxAction) && is_string($ajaxAction) && $ajaxAction !== '' ? $ajaxAction : null;
?>
<form id="<?= $h($formId) ?>"
      data-bulk-form
      data-select-all="<?= $h($selectAll) ?>"
      data-row-checkbox="<?= $h($rowSelector) ?>"
      data-action-select="<?= $h($actionSelect) ?>"
      data-apply-button="<?= $h($applyButton) ?>"
      data-counter="<?= $h($counter) ?>"
      method="post"
      data-ajax
      action="<?= $h($action) ?>"
      <?php if ($ajaxAction !== null): ?>data-action="<?= $h($ajaxAction) ?>"<?php endif; ?>>
  <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
  <?php foreach ($hidden as $name => $value): ?>
    <input type="hidden" name="<?= $h((string)$name) ?>" value="<?= $h((string)$value) ?>">
  <?php endforeach; ?>
</form>
