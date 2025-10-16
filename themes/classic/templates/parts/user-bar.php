<?php
/** @var array|null $frontUser */
/** @var \Cms\Utils\LinkGenerator $urls */

if (!$frontUser) {
    return;
}

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$name = (string)($frontUser['name'] ?? '');
$email = (string)($frontUser['email'] ?? '');
$role = (string)($frontUser['role'] ?? '');
$label = $name !== '' ? $name : ($email !== '' ? $email : 'Uživatel');
$isAdmin = $role === 'admin';
?>
<div class="user-bar">
  <div class="user-bar__inner">
    <div class="user-bar__section user-bar__section--left">
      <?php if ($isAdmin): ?>
        <a class="user-bar__logo" href="./admin.php">Administrace</a>
      <?php endif; ?>
      <a class="user-bar__link" href="<?= $h($urls->home()) ?>">Zobrazit web</a>
    </div>
    <div class="user-bar__section user-bar__section--right">
      <span class="user-bar__user"><?= $h($label) ?></span>
      <?php if ($isAdmin): ?>
        <a class="user-bar__link" href="./admin.php?r=posts&a=create">Nový příspěvek</a>
      <?php endif; ?>
      <a class="user-bar__link" href="<?= $h($urls->logout()) ?>">Odhlásit</a>
    </div>
  </div>
</div>
