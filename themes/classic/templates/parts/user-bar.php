<?php
/** @var array|null $frontUser */

if (!$frontUser) {
    return;
}

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$name = (string)($frontUser['name'] ?? '');
$email = (string)($frontUser['email'] ?? '');
$label = $name !== '' ? $name : ($email !== '' ? $email : 'Uživatel');
?>
<div class="user-bar">
  <div class="user-bar__inner">
    <div class="user-bar__section user-bar__section--left">
      <a class="user-bar__logo" href="./admin.php">Administrace</a>
      <a class="user-bar__link" href="./">Zobrazit web</a>
    </div>
    <div class="user-bar__section user-bar__section--right">
      <span class="user-bar__user"><?= $h($label) ?></span>
      <a class="user-bar__link" href="./admin.php?r=posts&a=create">Nový příspěvek</a>
      <a class="user-bar__link" href="./?r=logout">Odhlásit</a>
    </div>
  </div>
</div>
