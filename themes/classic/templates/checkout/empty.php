<?php
/** @var \Cms\Admin\Utils\LinkGenerator $links */
?>
<section class="section section--checkout-empty">
    <header class="section__header">
        <p class="section__eyebrow">Pokladna</p>
        <h1 class="section__title">Košík je prázdný</h1>
        <p class="section__lead">Než budete pokračovat na pokladnu, přidejte prosím do košíku alespoň jeden produkt.</p>
    </header>
    <p class="section__meta">
        <a class="button button--primary" href="<?= htmlspecialchars($links->products(), ENT_QUOTES, 'UTF-8'); ?>">Zobrazit produkty</a>
    </p>
</section>
