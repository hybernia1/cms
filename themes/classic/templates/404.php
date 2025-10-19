<?php /** @var \Cms\Admin\Utils\LinkGenerator $links */ ?>
<section class="section section--not-found">
    <header class="section__header">
        <p class="section__eyebrow">Chyba 404</p>
        <h1 class="section__title">Tuto stránku jsme nenašli</h1>
        <p class="section__lead">Omlouváme se, ale požadovaná stránka pravděpodobně neexistuje nebo byla přesunuta.</p>
    </header>

    <div class="notice notice--info">
        <p>
            Pokračujte prosím na <a href="<?= htmlspecialchars($links->home(), ENT_QUOTES, 'UTF-8'); ?>">úvodní stránku</a>
            nebo využijte vyhledávání v horní navigaci.
        </p>
    </div>
</section>
