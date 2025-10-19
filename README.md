# CMS – přehled

Tento projekt obsahuje produkčně použitelný instalační proces a kompletní administraci systému správy obsahu. Veškerá původní logika veřejné části byla odstraněna – k dispozici jsou pouze nástroje pro instalaci, konfiguraci a správu dat.

## Klíčové vlastnosti administrace

### Správa obsahu
- Podpora dvou primárních typů obsahu (`post` a `page`) včetně práce se stavem (`draft`/`publish`), automatického generování slugů a sledování časových metadat.
- Rychlé ukládání konceptů přímo z dashboardu a přehled posledních konceptů pro snadný návrat k rozpracované práci.

### Taxonomie a navigace
- Kategorie a štítky řešené jako termy s hromadným přiřazením k příspěvkům a filtrováním.
- Správa menu přes `NavigationController`, včetně definice vlastních položek a podporou více menu.

### Komentáře
- Moderace komentářů (schválení, spam, hromadné operace) s detailním pohledem na jednotlivé položky.
- Možnost odpovědi administrátora a přehled vazby na příspěvky.

### Média
- Upload souborů přes bezpečný `Core\Files\Uploader`, ukládání metadat a napojení médií na příspěvky.
- Generování WebP variant podle konfigurace a správa adresářové struktury uploadů.

### Uživatelské účty a bezpečnost
- Přihlášení chráněné CSRF tokeny a práce s rolí uživatele (aktuálně `admin`).
- Změna hesla, aktivace/deaktivace účtů a audit základních metadat.

### Nastavení systému
- Konfigurace permalinks, SEO URL, informací o webu, e-mailového odesílatele či SMTP.
- Správa šablon notifikačních e-mailů a možnost odeslat testovací zprávu.

### Témata (správa z administrace)
- Upload a aktivace témat ve formě ZIP archivu s `theme.json` manifestem.
- Přehled dostupných šablon v adresáři `themes/` (aktuálně prázdný – připravený pro nový frontend engine).

### Migrace a údržba
- Spouštění databázových migrací z administrace včetně přehledu jejich stavu.
- Detailní zobrazení případných chyb migrací a nástroj pro opětovné spuštění.

## Architektura a adresáře

### Kořenové skripty

| Soubor | Popis |
| --- | --- |
| `admin.php` | Bootstrap administrace. Načte prostředí, ověří přihlášení/roli a směruje požadavky na `Cms\Http\AdminController` nebo `AdminAuthController`. |
| `load.php` | Autoloader projektu (PSR‑4 + fallback pro názvy s podtržítky) a načtení globálních helperů. |
| `config.php` | Konfigurační pole s přístupem k databázi a dalšími volbami (vytváří instalátor). |

### Důležité adresáře

- `admin/` – Šablony, layouty a assets administrace (dashboard, komentáře, média, uživatelé, nastavení atd.).
- `helpers/` – Globální helpery dostupné ve views (např. funkce pro bezpečné escapování výstupu).
- `inc/` – Vlastní PHP knihovny a logika administrace.
  - `inc/Class/Core/` – Nízkoúrovňové komponenty: databázový wrapper (`Database\Init`, Query builder), správa souborů (`Files\PathResolver`, `Uploader`) a odesílání e-mailů (`Mail\Mailer`).
  - `inc/Class/Cms/` – Aplikační logika: autentizace (`AuthService`, `Authorization`), kontrolery administrace, doménové služby (`PostsService`, `UsersService`, `MediaService`, …) a validace.
  - `inc/resources/mail/` – PHP šablony e-mailů vracející `MailTemplate` objekty.
- `install/` – Vícekrokový instalátor (`install/index.php`) a SQL skripty (`tables.php`, `migrations.php`) pro zřízení databáze a počátečního administrátora.
- `themes/` – Prázdný adresář připravený pro budoucí frontend témata spravovaná z administrace.
- `uploads/` – Předpokládané místo pro nahrané soubory (není verzováno, ale používají ho uploady z administrace).

## Jak spolu části souvisejí
1. `admin.php` načte konfiguraci a databázi přes `Core\Database\Init`, zkontroluje session a deleguje požadavek na odpovídající controller v `Cms\Http\Admin`.
2. Controllery využívají služby z `Cms\Domain` pro práci s daty, `Cms\View\ViewEngine` k renderování šablon a utility z `Cms\Utils` (např. `LinkGenerator`, `Slugger`, `DateTimeFactory`).
3. Instalátor (`install/`) vytvoří `config.php`, základní tabulky i prvního administrátora, takže aplikaci lze rychle nasadit.

## Co bylo odstraněno
- Veřejný router, kontrolery a view-modely pro frontend.
- Předpřipravené šablony a témata původního frontendu (`themes/classic`, `themes/ocean`, `inc/templates/…`).
- Služby domény určené výhradně pro frontend (např. sitemap, strom komentářů, odesílání komentářů přes veřejné API).

Projekt je připraven jako základ pro nový frontend engine, přičemž administrační část zůstává plně funkční.
