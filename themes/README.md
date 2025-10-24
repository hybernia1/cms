# Struktura frontendových témat

Každé téma pro veřejnou část žije v adresáři `themes/<slug>` a obsahuje alespoň
složku `templates/` s PHP šablonami. Doporučené soubory:

```
themes/
└── moje-tema/
    ├── templates/
    │   ├── layout.php
    │   ├── home.php
    │   ├── single.php
    │   ├── page.php
    │   ├── category.php
    │   ├── tag.php
    │   └── search.php
    ├── assets/
    │   └── style.css
    └── functions.php
```

* `layout.php` – základní kostra HTML, uvnitř volá `$content()` pro vložení obsahu.
* Ostatní šablony vykreslují konkrétní routy (domovská stránka, detail článku, ...).
* Soubor `functions.php` je volitelný; pokud existuje, načte se při inicializaci tématu.
  Můžete si v něm připravit vlastní helpery nebo hooky.

View engine do šablon automaticky předává tyto proměnné:

* `$site` – informace o webu (`title`, `url`, případně `description`).
* `$links` – instance `Cms\Admin\Utils\LinkGenerator` pro stavbu URL.
* `$navigation` – pole menu seskupených podle umístění (`primary`, ...).
* `$meta` – metadata aktuální stránky (`title`, `description`, `canonical`).
* `$theme['asset']($relPath)` – closure vracející URL k souborům tématu.

Pokud šablona chybí, renderer vyhodí výjimku (např. `RuntimeException`). Doporučený
postup je doplnit vlastní šablonu do aktivního tématu nebo v administraci přepnout
na jiné téma, které požadovaný template obsahuje. Během vývoje můžete pro rychlou
diagnostiku zapnout logování chyb v `config.php` (pole `debug`).
