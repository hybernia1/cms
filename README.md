# CMS – přehled

Tento projekt je modulární PHP CMS se samostatně vystavěnou administrací, frontendovými šablonami a instalátorem. Níže najdeš souhrn hlavních vlastností a orientaci v kódu.

## Klíčové vlastnosti

### Správa obsahu
- Podpora dvou primárních typů obsahu (`post` a `page`) včetně automatického generování a udržování unikátních slugů, práce se stavem (`draft`/`publish`) a sledování časových metadat.
- Rychlé ukládání konceptů přímo z dashboardu administrace a přehled posledních konceptů pro snadný návrat k rozpracované práci.

### Taxonomie, navigace a struktura webu
- Kategorie a štítky řešené jako termy s možností hromadného přiřazení k příspěvkům a filtrování archivů podle typu termu.
- Správa menu přes `NavigationController` a repository, které skládají strom navigace pro frontendové šablony (např. primární menu).

### Komentáře a interakce návštěvníků
- Vkládání komentářů přihlášených i anonymních uživatelů, validace vstupu a evidence stavů (`draft`, `published`, `spam`, `trash`) včetně zaznamenání IP/UA pro audit.
- Veřejná část poskytuje endpoint pro odeslání komentáře a šablony pro výpis i formulář jsou sdílené v `inc/templates/parts` pro snadnou úpravu vzhledu.

### Média a soubory
- Upload souborů přes bezpečný `Core\Files\Uploader`, ukládání metadat a napojení médií na příspěvky včetně automatické konverze do WebP podle nastavení CMS.
- Robustní resolver cest, který hlídá kořen uploadů a umožňuje generovat absolutní/relativní cesty bez rizika traversalu.

### Uživatelské účty a bezpečnost
- Přihlášení sdílené mezi frontendem a administrací, hashování hesel a práce s rolí uživatele (aktuálně role `admin`).
- Administrace chráněná CSRF tokeny a schopná vracet HTML i JSON odpovědi pro AJAX požadavky, což usnadňuje tvorbu interaktivních formulářů.

### Šablony, témata a frontend
- Správa témat přes manifest `theme.json`, podporu dědičnosti a více template cest – `ThemeManager` načte hierarchii a předá ji renderovacímu enginu.
- Jednoduchý, ale bezpečný `ViewEngine` s partialy (podobné `get_template_part`) a sdílenými daty, který kontroluje reálné cesty šablon.
- `FrontController` zajišťuje směrování bez .htaccess – umí domovskou stránku, single, archivy, vyhledávání, přihlášení/registraci, sitemap index a sekce i odesílání komentářů.
- Výchozí téma `themes/classic` obsahuje stránky pro login, registraci, reset hesla, archivy, single, home i chybové šablony a definuje assets/regiony v manifestu.

### SEO, vyhledávání a odkazy
- Nastavitelné permalinkové báze (post/page/tag/category) a možnost SEO URL přepínače přímo v nastavení CMS.
- Sitemapy včetně indexu a sekcí pro příspěvky, stránky, kategorie a tagy generované nad daty databáze a LinkGeneratoru.
- Fulltext vyhledávání přes `FrontController::search`, které reaguje na dotaz `s` a využívá sdílené šablony a permalinky.

### E-maily a notifikace
- `MailService` se spínáním mezi PHP `mail()` a SMTP, přidáním podpisu z nastavení a automatickým textovým fallbackem.
- Správa šablon e-mailů (registrace, aktivace, reset hesla…) přes `TemplateManager`, který umí vracet objekt `MailTemplate` z PHP souborů v `inc/resources/mail`.

### Instalace a údržba
- Vícekrokový instalátor s generováním `config.php`, vytvořením tabulek a vytvořením prvního administrátora, doplněný o CLI migrátor pro další strukturované změny databáze.
- Migrace pokrývají nastavení, navigaci, média a další entity; jsou pojmenované dle data a lze je spouštět postupně.

### Administrace a UI
- Modulární controller pro administraci směruje na jednotlivé oblasti (příspěvky, média, termy, komentáře, témata, menu, nastavení, migrace, uživatelé).
- Navigace administrace se generuje z jednoho místa (`AdminNavigation`) a front-end assets jsou definované manifestem s Bootstrapem a vlastními styly/js, včetně jednoduchého blokového editoru pro obsah a štítky.

### Základní knihovny
- Vlastní query builder pro CRUD operace, joiny, where/limit a bezpečné bindy, který stojí nad `PDO`.
- Ukládání souborů a MIME detekce v `Core\Files`, odesílání e-mailů v `Core\Mail` a utility pro slugy, datumy, permalinky a generování URL (`LinkGenerator`).

## Architektura a adresáře

### Kořenové skripty

| Soubor | Popis |
| --- | --- |
| `index.php` | Vstup pro veřejnou část webu – spouští autoloader, inicializuje databázi a předává řízení `Cms\Http\FrontController`, který směruje požadavky na příslušné šablony nebo akce. |
| `admin.php` | Bootstrap administrace. Po načtení prostředí kontroluje přihlášení uživatele a role a směruje požadavky na `Cms\Http\AdminController` nebo `AdminAuthController`. |
| `load.php` | Jediný autoloader projektu (PSR‑4 + fallback na názvy s podtržítky). Zajišťuje načtení tříd z `inc/Class` a připojení helperů. |
| `config.php` | Konfigurační pole pro běh aplikace, především přístup k databázi a flag ladicího režimu. |
| `login.php` | Samostatná ukázková stránka s formulářem pro přihlášení přes `Cms\Auth\AuthService`, slouží i k testování CSRF ochran. |
| `post.php` | Jednoduchý CRUD formulář pro vytvoření příspěvku přihlášeným uživatelem. Využívá služby domény (např. `PostsService`, `MediaService`) a správu uploadů z `Core\Files`. |

### Důležité adresáře

- `admin/` – Šablony, layouty a assets administrace (dashboard, komentáře, média, uživatelé, nastavení atd.).
- `helpers/` – Globální helpery dostupné ve views (např. funkce pro render partialů).
- `inc/` – Vlastní PHP knihovny a sdílené šablony.
  - `inc/Class/Core/` – Nízkoúrovňové komponenty: databázový wrapper (`Database\Init`, Query builder), správa souborů (`Files\PathResolver`, `Uploader`) a odesílání e-mailů (`Mail\Mailer`).
  - `inc/Class/Cms/` – Aplikační logika CMS. Obsahuje autentizaci/autorizační vrstvu, HTTP controllery (frontend i admin), doménové služby a repository pro práci s entitami (příspěvky, média, komentáře, uživatelé), správu témat, validaci a renderování view.
  - `inc/resources/mail/` – PHP šablony e-mailů vracející `MailTemplate`.
  - `inc/templates/` – Sdílené partialy pro frontend (komentáře, vyhledávání…).
- `install/` – Vícekrokový instalátor (`install/index.php`) a SQL skripty (`tables.php`, `migrations/`) pro zřízení databáze, plus pomocný skript `migrations.php`.
- `themes/` – Veřejné šablony webu. Výchozí téma `classic/` obsahuje šablony používané `ThemeResolver`em a `ViewEngine`m, manifest s assets a definicí regionů.
- `uploads/` – Předpokládané místo pro nahrané soubory (není verzováno, ale používají ho demo skripty).

## Jak spolu části souvisejí
1. Vstupní skripty (`index.php`, `admin.php`) načtou konfiguraci a databázi přes `Core\Database\Init` a předají řízení controllerům v `Cms\Http`.
2. Controllery používají služby z `Cms\Domain` pro načítání a ukládání dat, `Cms\View` + `Cms\Theming` pro renderování šablon a `Cms\Utils\LinkGenerator` pro generování URL.
3. Uploady a práce se soubory zajišťují komponenty `Core\Files`, autentizaci a autorizaci řeší `Cms\Auth`, notifikace e-mailem `Cms\Mail`.
4. Instalátor vytvoří `config.php`, potřebné tabulky a prvního administrátora, takže produkční prostředí lze nasadit bez ručního zásahu do kódu.
