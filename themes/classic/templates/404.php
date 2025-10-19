<?php /** @var \Cms\Admin\Utils\LinkGenerator $links */ ?>
<section>
    <header>
        <h1>404 – Stránka nenalezena</h1>
    </header>
    <p>Vypadá to, že jste se zatoulali mimo mapu. Zkuste začít znovu na <a href="<?= htmlspecialchars($links->home(), ENT_QUOTES, 'UTF-8'); ?>">úvodní stránce</a>.</p>
</section>
