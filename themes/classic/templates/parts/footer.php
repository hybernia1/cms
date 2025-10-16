<?php
/** @var string $siteTitle */
/** @var \Cms\Utils\LinkGenerator $urls */

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<footer class="site-footer">
  <div class="site-footer__brand">&copy; <?= date('Y') ?> <?= $h($siteTitle) ?></div>
  <div class="site-footer__links">
    <a href="<?= $h($urls->login()) ?>">Přihlášení</a>
    <a href="<?= $h($urls->register()) ?>">Registrace</a>
    <a href="<?= $h($urls->terms()) ?>">Termy</a>
  </div>
</footer>
