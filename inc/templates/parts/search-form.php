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
?>
<form class="<?= e((string)$classes['form']) ?>" method="get" action="<?= e($action) ?>">
  <input
    type="text"
    class="<?= e((string)$classes['input']) ?>"
    name="s"
    value="<?= e($query) ?>"
    placeholder="<?= e((string)$strings['placeholder']) ?>"
  >
  <button class="<?= e((string)$classes['button']) ?>" type="submit"><?= e((string)$strings['submit']) ?></button>
</form>
