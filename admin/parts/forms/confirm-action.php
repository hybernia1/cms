<?php
declare(strict_types=1);

/**
 * @var string $action
 * @var string $csrf
 * @var array<string,scalar|array|null> $hidden
 * @var string|null $formClass
 * @var array{class?:string,icon?:string,text?:string,ariaLabel?:string,tooltip?:string,type?:string}|null $button
 * @var array<string,string|int|float|bool|null> $dataAttributes
 * @var array{message?:string,title?:string,confirm?:string,cancel?:string}|null $confirm
 * @var bool|null $ajax
 */

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$action = (string)($action ?? '#');
$csrf = (string)($csrf ?? '');
$hidden = is_array($hidden ?? null) ? $hidden : [];
$formClass = trim('d-inline ' . (string)($formClass ?? ''));
$button = $button ?? [];
$buttonClass = trim('btn btn-light btn-sm border ' . (string)($button['class'] ?? ''));
$buttonIcon = (string)($button['icon'] ?? '');
$buttonText = (string)($button['text'] ?? '');
$buttonType = (string)($button['type'] ?? 'submit');
$buttonTooltip = (string)($button['tooltip'] ?? '');
$buttonAria = (string)($button['ariaLabel'] ?? ($buttonTooltip !== '' ? $buttonTooltip : $buttonText));
$ajax = ($ajax ?? true) ? ' data-ajax' : '';
$dataAttributes = is_array($dataAttributes ?? null) ? $dataAttributes : [];
$confirm = is_array($confirm ?? null) ? $confirm : [];
$confirmMessage = (string)($confirm['message'] ?? '');
$confirmTitle = (string)($confirm['title'] ?? '');
$confirmConfirm = (string)($confirm['confirm'] ?? '');
$confirmCancel = (string)($confirm['cancel'] ?? '');

$renderHiddenInput = static function (string $name, $value) use (&$renderHiddenInput, $h): string {
    if (is_array($value)) {
        $html = '';
        foreach ($value as $index => $item) {
            $html .= $renderHiddenInput($name . '[' . $index . ']', $item);
        }
        return $html;
    }
    $stringValue = match (true) {
        is_bool($value) => $value ? '1' : '0',
        $value === null => '',
        default => (string)$value,
    };
    return '<input type="hidden" name="' . $h($name) . '" value="' . $h($stringValue) . '">';
};
?>
<form method="post"
      action="<?= $h($action) ?>"
      class="<?= $h($formClass) ?>"<?= $ajax ?>
<?php foreach ($dataAttributes as $attr => $value):
    if (!is_string($attr) || $attr === '') {
        continue;
    }
    $stringValue = match (true) {
        is_bool($value) => $value ? 'true' : 'false',
        $value === null => '',
        default => (string)$value,
    };
?>      <?= $h($attr) ?>="<?= $h($stringValue) ?>"
<?php endforeach; ?><?php if ($confirmMessage !== ''): ?>
      data-confirm-modal="<?= $h($confirmMessage) ?>"<?php endif; ?><?php if ($confirmTitle !== ''): ?>
      data-confirm-modal-title="<?= $h($confirmTitle) ?>"<?php endif; ?><?php if ($confirmConfirm !== ''): ?>
      data-confirm-modal-confirm-label="<?= $h($confirmConfirm) ?>"<?php endif; ?><?php if ($confirmCancel !== ''): ?>
      data-confirm-modal-cancel-label="<?= $h($confirmCancel) ?>"<?php endif; ?>>
  <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
  <?php foreach ($hidden as $name => $value):
      if (!is_string($name) || $name === '') {
          continue;
      }
      echo $renderHiddenInput($name, $value);
  endforeach; ?>
  <button class="<?= $h($buttonClass) ?>"
          type="<?= $h($buttonType) ?>"<?php if ($buttonTooltip !== ''): ?>
          data-bs-toggle="tooltip"
          data-bs-title="<?= $h($buttonTooltip) ?>"<?php endif; ?><?php if ($buttonAria !== ''): ?>
          aria-label="<?= $h($buttonAria) ?>"<?php endif; ?>>
    <?php if ($buttonIcon !== ''): ?>
      <i class="<?= $h($buttonIcon) ?>"></i>
    <?php endif; ?>
    <?php if ($buttonText !== ''): ?>
      <span><?= $h($buttonText) ?></span>
    <?php endif; ?>
  </button>
</form>
