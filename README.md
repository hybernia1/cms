# CMS – přehled souborů

Tento projekt je modulární PHP CMS. Níže najdeš rychlou orientaci v důležitých souborech a adresářích.

## Kořenové skripty

| Soubor | Popis |
| --- | --- |
| `index.php` | Vstup pro veřejnou část webu – spouští autoloader, inicializuje databázi a předává řízení `Cms\Http\FrontController`, který směruje požadavky na příslušné šablony nebo akce. |
| `admin.php` | Bootstrap administrace. Po načtení prostředí kontroluje přihlášení uživatele a role a směruje požadavky na `Cms\Http\AdminController` nebo `AdminAuthController`. |
| `load.php` | Jediný autoloader projektu (PSR‑4 + fallback na názvy s podtržítky). Zajišťuje načtení tříd z `inc/Class` a připojení helperů. |
| `config.php` | Konfigurační pole pro běh aplikace, především přístup k databázi a flag ladicího režimu. |
| `login.php` | Samostatná ukázková stránka s formulářem pro přihlášení přes `Cms\Auth\AuthService`, slouží i k testování CSRF ochran. |
| `post.php` | Jednoduchý CRUD formulář pro vytvoření příspěvku přihlášeným uživatelem. Využívá služby domény (např. `PostsService`, `MediaService`) a správu uploadů z `Core\Files`. |
| `test.php` | Integrační test jádra – kombinuje databázovou vrstvu a správu souborů, včetně fallbacku na vytvoření potřebných tabulek. |

## Adresáře

### `admin/`
Šablony a layouty administrace. `admin/index.php` pouze přesměruje na `admin.php`, skutečné šablony jsou v `admin/views` (dashboard, komentáře, média, uživatelé, nastavení atd.).

### `inc/`
Vlastní PHP knihovny.
- `inc/Class/Core/` – Nízkoúrovňové komponenty: databázový wrapper (`Database\Init`, Query builder), správa souborů (`Files\PathResolver`, `Uploader`) a odesílání e-mailů (`Mail\Mailer`).
- `inc/Class/Cms/` – Aplikační logika CMS. Obsahuje služby autentizace a autorizace, HTTP controllery (frontend a admin), doménové služby a repository pro práci s entitami (příspěvky, média, komentáře, uživatelé), správu témat, validaci a renderování view.

### `install/`
Vícekrokový instalátor (`install/index.php`) a SQL skripty (`tables.php`, `migrations/`) pro zřízení databáze, plus pomocný skript `migrations.php`.

### `themes/`
Veřejné šablony webu. Výchozí téma `classic/` obsahuje Twig/latte-like šablony používané `ThemeResolver`em a `ViewEngine`m.

### Další adresáře
- `uploads/` – Předpokládané místo pro nahrané soubory (není verzováno, ale používají ho demo skripty).

## Jak spolu části souvisejí
1. Vstupní skripty (`index.php`, `admin.php`) načtou konfiguraci a databázi přes `Core\Database\Init` a předají řízení controllerům v `Cms\Http`.
2. Controllery používají služby z `Cms\Domain` pro načítání a ukládání dat a `Cms\View` + `Cms\Theming` pro renderování šablon.
3. Uploady a práce se soubory zajišťují komponenty `Core\Files`, autentizaci a autorizaci řeší `Cms\Auth`.
4. Instalátor vytvoří `config.php`, potřebné tabulky a prvního administrátora, takže produkční prostředí lze nasadit bez ručního zásahu do kódu.
