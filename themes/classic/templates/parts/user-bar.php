<?php
/** @var array|null $frontUser */
/** @var \Cms\Utils\LinkGenerator $urls */

if (!$frontUser) {
    return;
}

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$name  = (string)($frontUser['name'] ?? 'Uživatel');
$email = (string)($frontUser['email'] ?? '');
$role  = (string)($frontUser['role'] ?? '');
$isAdmin = $role === 'admin';
?>
<div class="user-bar">
  <div class="user-bar__inner">
    <div class="user-bar__info">
      <span class="user-bar__label">Přihlášen:</span>
      <span class="user-bar__value"><?= $h($name !== '' ? $name : $email) ?></span>
    </div>
    <div class="user-bar__links">
      <?php if ($isAdmin): ?>
        <a class="user-bar__action" href="<?= $h($urls->admin()) ?>">Administrace</a>
        <a class="user-bar__action" href="<?= $h($urls->admin()) ?>?r=posts&amp;a=create">Nový příspěvek</a>
      <?php endif; ?>
      <a class="user-bar__action" href="<?= $h($urls->home()) ?>">Zobrazit web</a>
      <a class="user-bar__action" href="<?= $h($urls->logout()) ?>">Odhlásit</a>
    </div>
  </div>
</div>
