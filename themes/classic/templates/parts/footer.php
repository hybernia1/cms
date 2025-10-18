<?php
/** @var string $siteTitle */
/** @var \Cms\Utils\LinkGenerator $urls */
?>
<footer class="site-footer">
  <div class="site-footer__brand">&copy; <?= date('Y') ?> <?= e($siteTitle) ?></div>
  <div class="site-footer__links">
    <a href="<?= e($urls->login()) ?>">Přihlášení</a>
    <a href="<?= e($urls->register()) ?>">Registrace</a>
    <a href="<?= e($urls->terms()) ?>">Termy</a>
  </div>
</footer>
