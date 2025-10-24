# Post meta architektura

## Cíle
- Umožnit libovolným post typům ukládat strukturovaná data bez nutnosti přidávat sloupce do tabulky `posts`.
- Zachovat jednoduché použití ("přidat klíč a hodnotu") a současně nabídnout deklarativní registraci, aby administrace znala dostupná pole.
- Minimalizovat počet dotazů při čtení seznamů (post seznamy, navigace, REST) a zamezit kolizím mezi typy.

## Datová vrstva
1. **Nová tabulka `post_meta`**
   ```sql
   CREATE TABLE post_meta (
     post_id BIGINT UNSIGNED NOT NULL,
     meta_key VARCHAR(191) NOT NULL,
     meta_type VARCHAR(50) NOT NULL DEFAULT 'string',
     meta_value LONGTEXT NULL,
     created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
     updated_at DATETIME NULL,
     PRIMARY KEY (post_id, meta_key),
     INDEX ix_post_meta_key (meta_key),
     INDEX ix_post_meta_type (meta_type)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
   ```
   - Primární klíč `(post_id, meta_key)` zajistí idempotentní ukládání a rychlé `UPDATE` při změnách.
   - `meta_type` pomůže s validací/detekcí serializace (JSON, integer, boolean).
   - `meta_value` doporučeně ukládat ve formátu JSON pro složitější struktury, jednoduché typy sanitovat na řetězce/čísla.

2. **Repository vrstvy**
   - `Cms\Admin\Domain\Repositories\PostMetaRepository` s metodami `loadForPosts(array $ids)`, `get(int $postId, string $key)`, `upsert(...)`, `delete(...)`.
   - Slučuje načítání meta pro více příspěvků, aby `PostsRepository::paginate()` mohlo připojit metadata jedním dotazem. Při seznamu ID např. `SELECT * FROM post_meta WHERE post_id IN (...)` a následné seskupení v PHP.

## Registr metaklíčů
- Samostatná třída `Cms\Admin\Domain\PostMeta\PostMetaRegistry` inspirovaná `PostTypeRegistry`.
- API na úrovni helperů v `inc/functions/post-meta.php`:
  ```php
  register_post_meta('event', 'starts_at', [
      'type' => 'datetime',
      'sanitize_callback' => static fn($value) => \DateTimeImmutable::createFromFormat(...),
      'default' => null,
      'label' => 'Začátek',
      'show_in_admin' => true,
  ]);
  ```
- Registrace se uloží podle klíče `{$postType}:{$metaKey}`. Díky tomu lze jedním helperem definovat globální metaklíč (`register_post_meta('post', 'seo_title', ...)`) i sdílený (`register_shared_post_meta('seo_title', ...)`), který se promítne do více typů.
- Registry poskytne metody `forType(string $postType): array`, `definition(string $postType, string $key): ?array` a validaci hodnot.
- Do `PostTypeRegistry::register()` lze přidat volitelný klíč `supports_meta => ['seo', 'events']`, aby bylo možné deklarativně povolit konkrétní skupiny. Registry pak nabídne hromadnou registraci podle skupin (např. `register_meta_group('seo', [...])`).

## Runtime API
- Helpery `get_post_meta(int $postId, string $key, mixed $default = null): mixed`, `update_post_meta(int $postId, string $key, mixed $value): void`, `delete_post_meta(...)`.
- Pro dávkové získání metadat (např. ve frontendu) nabídnout `get_posts_meta(array $postIds): array` → využije `PostMetaRepository::loadForPosts()` a vrátí asociativní pole `[postId => ['key' => value, ...]]`.
- Validace vstupů při `update` proběhne přes registry: pokud klíč existuje, provede se sanitace a kontrola typu, jinak buď povolit "volné" uložení (kompatibilita) nebo vyhodit chybu podle konfigurace (`'strict' => true`).
- Serializaci doporučit přes `json_encode` s validací typu (`meta_type` = `json`). Jednoduché typy (`boolean`, `integer`) lze ukládat přímo jako řetězce, aby fungovaly indexy.

## Integrace do administrace
1. **Post editor**
   - Při načtení editoru (`Cms\Admin\Http\Controllers\PostsController::edit`) načíst přes registry definovaná pole pro daný typ (`PostTypeRegistry::get($type)` + `PostMetaRegistry::forType($type)`) a předat do šablony.
   - JavaScript (`admin/assets/js/ui/post-editor.js`) může dynamicky vykreslovat definované fieldsety (text, textarea, select, media picker) podle typu metaklíče.
   - Při `POST` validaci využít `PostMetaRegistry::sanitize($type, $key, $value)` a uložit přes `PostMetaRepository`.

2. **Seznamy a filtry**
   - Pokud některá metadata mají být vidět ve výpisech (např. datum akce), registry může obsahovat `list_column` definici. Controller následně připojí hodnoty přes dávkové načtení.
   - Do rychlého filtrování lze využít `meta_type` + JSON_EXTRACT, ale doporučené je pro často filtrované hodnoty přidat virtuální sloupce (např. `meta_value_int`) nebo pomocné tabulky.

3. **REST / front data**
   - `Cms\Front\Http\Router` nebo view layer (`ThemeViewEngine`) může využít `get_posts_meta()` pro hromadné načtení metadat, aby se předešlo N+1 dotazům. Výsledek se následně vloží do `$meta` pole předávaného šablonám.

## Migrace a kompatibilita
- Přidat instalační SQL do `install/tables.php` a doplnit migraci pro existující instalace.
- Pokud již existují "volná" metadata (např. v JSON sloupci), lze vytvořit migraci, která je rozdělí do `post_meta` a k jednotlivým klíčům doregistruje definice.
- Pro import/export nabídnout bulk endpoint (`PostsController::bulkMetaExport()`), který obslouží CSV/JSON.

## Bezpečnost a výkon
- Sanitace musí probíhat před uložením – registry by měla povolovat callbacky i jednoduché deklarace (`'type' => 'url'`).
- Zamezit injekci meta klíčů do frontendu: před výpisem prohnat přes `htmlspecialchars`, resp. u JSON dat validovat schema.
- Cachování: Po načtení metadat pro post je vhodné uložit do `RuntimeCache` (pokud existuje; v projektu se používá např. `Core\Cache\RuntimeCache`), aby se opakovaně nepřistupovalo do DB.

## Inspirace z WordPress
- `register_post_meta()` a `register_meta()` definují typ, sanitaci a REST viditelnost – totéž zde přenést přes registry.
- WordPress ukládá vše jako `longtext` + serializaci. Zde lze být přísnější díky `meta_type` a JSON, čímž odpadne ruční unserialize.
- Při vyžádání všech metadat WP vrací asociativní pole; doporučuji stejný interface pro snadnou orientaci.

## Další kroky
1. Vytvořit základ registru + repository vrstvu s testy pro CRUD.
2. Implementovat helpery a integrovat do `PostsService` tak, aby se meta načítala spolu s postem (`find`, `findBySlug`).
3. Rozšířit post editor o dynamická pole a REST výstupy o meta sekci.
4. Připravit dokumentaci pro autory/šablony, jak metadata registrovat a používat.
