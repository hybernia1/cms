<?php
declare(strict_types=1);
/** @var string|null $query */
/** @var string|null $action */
/** @var array<string,string>|null $classes */
/** @var array<string,string>|null $strings */

$query   = isset($query) ? (string)$query : '';
$action  = isset($action) && $action !== '' ? (string)$action : './?r=search';
$classes = is_array($classes ?? null) ? $classes : [];
$strings = is_array($strings ?? null) ? $strings : [];

$classDefaults = [
    'form'   => 'search-form',
    'input'  => 'search-form__input',
    'button' => 'btn btn--primary',
];
$classes = $classes + $classDefaults;

$stringDefaults = [
    'placeholder' => 'Co hledáte?',
    'submit'      => 'Hledat',
];
$strings = $strings + $stringDefaults;

$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<form class="<?= $esc((string)$classes['form']) ?>" method="get" action="<?= $esc($action) ?>">
  <input
    type="text"
    class="<?= $esc((string)$classes['input']) ?>"
    name="s"
    value="<?= $esc($query) ?>"
    placeholder="<?= $esc((string)$strings['placeholder']) ?>"
  >
  <button class="<?= $esc((string)$classes['button']) ?>" type="submit"><?= $esc((string)$strings['submit']) ?></button>
</form>
