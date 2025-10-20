# Admin pseudo-AJAX audit

Tento přehled mapuje všechna současná `data-ajax` odesílání v administraci, jejich backendové handlery a reakce. Výsledky slouží jako výchozí bod pro migraci na skutečné JSON endpointy.

## Front-end driver
- Skript [`admin/assets/js/admin.js`](../admin/assets/js/admin.js) zachytává formuláře a odkazy s `data-ajax`, odesílá je pomocí `fetch` a očekává JSON odpověď.
- Úspěšné odpovědi dnes obvykle obsahují:
  - `html` + volitelný `title` (render celé stránky) – vrací jej `BaseAdminController::renderAdmin()`.
  - nebo `redirect` + volitelný `flash` (po úspěšném POSTu) – generuje `BaseAdminController::redirect()`.
  - výjimkou je autosave (`posts_autosave`), který vrací vlastní JSON payload.

## `auth` (přihlášení)
| UI místo | Endpoint | Metoda | Klíčové parametry | Handler | Současná odpověď |
| --- | --- | --- | --- | --- | --- |
| `admin/views/auth/login.php` | `admin.php?r=auth&a=login` | POST | `email`, `password`, `remember`, `csrf` | `AdminAuthController::login()` | `success+redirect` při úspěchu, `success:false` s `flash` při chybě. |

## `dashboard`
| UI místo | Endpoint | Metoda | Klíčové parametry | Handler | Současná odpověď |
| --- | --- | --- | --- | --- | --- |
| Widget „Rychlý koncept“ (`admin/parts/widget/quick-draft.php`) | `admin.php?r=dashboard&a=quick-draft` | POST | `title`, `content`, fixní `type=post`, `csrf` | `AdminController::dashboardQuickDraft()` | Redirect na `admin.php?r=dashboard` + flash. |

## `posts`
| UI místo | Endpoint | Metoda | Klíčové parametry | Handler | Současná odpověď |
| --- | --- | --- | --- | --- | --- |
| Toolbar filtr/seach (`admin/views/posts/index.php` → `parts/listing/toolbar.php`) | `admin.php` (`r=posts`, `type`, volitelně `status`,`author`,`q`) | GET | `q`, `status`, `author`, `type`, `page` | `PostsController::index()` | Vrací HTML (`renderAdmin`). |
| Bulk akce (`parts/listing/bulk-form.php`) | `admin.php?r=posts&a=bulk&type=…` | POST | `bulk_action`, `ids[]`, `csrf`, zachované filtry | `PostsController::bulk()` | Redirect na listing + flash. |
| Přepnutí stavu řádku | `admin.php?r=posts&a=toggle&type=…` | POST | `id`, `csrf` | `PostsController::toggleStatus()` | Redirect na listing + flash. |
| Smazání řádku | `admin.php?r=posts&a=delete&type=…` | POST | `id`, `csrf` | `PostsController::delete()` | Redirect na listing + flash. |
| Formulář editace/vytvoření (`admin/views/posts/edit.php`) | `admin.php?r=posts&a=create|edit&type=…` | POST (multipart) | Obsahuje `title`, `slug` (edit), `status`, `content`, `comments_allowed`, výběr termů (`categories[]`,`tags[]`,`new_*`), přiložená média (`attached_media`), `selected_thumbnail_id`, `remove_thumbnail`, upload `thumbnail`, `csrf` | `PostsController::store()` / `update()` | Redirect na detail s flash (`success`/`danger`). |
| Autosave (trigger přes `data-autosave-url`) | `admin.php?r=posts&a=autosave&type=…` | POST (AJAX skriptem) | Shodná data jako editace + `id/post_id`, `status` | `PostsController::autosave()` | JSON `{success,message,postId,status,statusLabel,actionUrl,…}` nebo `success:false` s chybou. |

## `terms`
| UI místo | Endpoint | Metoda | Klíčové parametry | Handler | Současná odpověď |
| --- | --- | --- | --- | --- | --- |
| Toolbar filtr (`terms/index.php`) | `admin.php` (`r=terms`, `type`, `q`) | GET | `type`, `q`, `page` | `TermsController::index()` | HTML (`renderAdmin`). |
| Bulk formulář | `admin.php?r=terms&a=bulk&type=…` | POST | `bulk_action`, `ids[]`, `csrf` | `TermsController::bulk()` | Redirect na listing + flash. |
| Formulář create/edit (`terms/edit.php`) | `admin.php?r=terms&a=create|edit&type=…` | POST | `name`, `slug`, `description`, u create `type`, `csrf` | `TermsController::store()` / `update()` | Redirect (na edit/list) + flash. |
| Smazání z přehledu (`terms/index.php` – inline formuláře) | `admin.php?r=terms&a=delete&type=…` | POST | `id`, `csrf` | `TermsController::delete()` | Redirect na listing + flash. |

## `media`
| UI místo | Endpoint | Metoda | Klíčové parametry | Handler | Současná odpověď |
| --- | --- | --- | --- | --- | --- |
| Filtr panel (`media/index.php`) | `admin.php` (`r=media`, `type`, `q`) | GET | `type`, `q`, `page` | `MediaController::index()` | HTML (`renderAdmin`). |
| Modal nahrávání | `admin.php?r=media&a=upload` | POST (multipart) | `files[]`, `csrf` | `MediaController::upload()` | Redirect na listing + flash (`success` nebo `danger`). |
| Optimalizace WebP | `admin.php?r=media&a=optimize` | POST | `id`, `csrf` | `MediaController::optimize()` | Redirect na listing + flash (`success/info/danger`). |
| Smazání souboru | `admin.php?r=media&a=delete` | POST | `id`, `csrf` | `MediaController::delete()` | Redirect na listing + flash. |

## `comments`
| UI místo | Endpoint | Metoda | Klíčové parametry | Handler | Současná odpověď |
| --- | --- | --- | --- | --- | --- |
| Toolbar filtr (`comments/index.php`) | `admin.php` (`r=comments`, `status`,`q`,`post`) | GET | `status`, `q`, `post`, `page` | `CommentsController::index()` | HTML (`renderAdmin`). |
| Bulk formulář | `admin.php?r=comments&a=bulk` | POST | `bulk_action`, `ids[]`, `csrf`, zachované filtry (`status`,`q`,`post`,`page`) | `CommentsController::bulk()` | Redirect na listing + flash. |
| Detail – změna stavu | `admin.php?r=comments&a=approve|draft|spam` | POST | `id`, `_back` (redirect url), `csrf` | `CommentsController::setStatus()` | Redirect na `_back` + flash. |
| Detail – smazání | `admin.php?r=comments&a=delete` | POST | `id`, `_back`, `csrf` | `CommentsController::delete()` | Redirect na `_back` + flash. |
| Detail – odpověď admina | `admin.php?r=comments&a=reply` | POST | `parent_id`, `content`, `csrf` | `CommentsController::reply()` | Redirect na thread detail + flash. |

## `users`
| UI místo | Endpoint | Metoda | Klíčové parametry | Handler | Současná odpověď |
| --- | --- | --- | --- | --- | --- |
| Toolbar vyhledávání (`users/index.php`) | `admin.php` (`r=users`) | GET | `q`, `page` | `UsersController::index()` | HTML (`renderAdmin`). |
| Bulk mazání | `admin.php?r=users&a=bulk` | POST | `ids[]`, `csrf`, filtry (`q`,`page`) | `UsersController::bulk()` | Redirect na seznam + flash. |
| Formulář create/edit (`users/edit.php`) | `admin.php?r=users&a=save` | POST | `id` (při editaci), `name`, `email`, `role`, `active`, `password`, `csrf` | `UsersController::save()` | Redirect na seznam (`success`) nebo zpět na form s chybou. |
| Odeslání templ. e-mailu (`users/edit.php`) | `admin.php?r=users&a=send-template` | POST | `id`, `template`, `csrf` | `UsersController::sendTemplate()` | Redirect zpět na edit + flash. |

## `navigation`
| UI místo | Endpoint | Metoda | Klíčové parametry | Handler | Současná odpověď |
| --- | --- | --- | --- | --- | --- |
| Úprava menu | `admin.php?r=navigation&a=update-menu` | POST | `id`, `name`, `slug`, `location`, `description`, `csrf` | `NavigationController::updateMenu()` | Redirect zpět na `menu_id` + flash. |
| Smazání menu | `admin.php?r=navigation&a=delete-menu` | POST | `id`, `csrf` | `NavigationController::deleteMenu()` | Redirect na seznam + flash. |
| Vytvoření menu | `admin.php?r=navigation&a=create-menu` | POST | `name`, `slug`, `location`, `description`, `csrf` | `NavigationController::createMenu()` | Redirect na nové menu + flash. |
| Formulář položky | `admin.php?r=navigation&a=create-item` / `update-item` | POST | `menu_id`, `id` (u editace), `title`, `link_type`, `link_reference`, `url`, `target`, `css_class`, `parent_id`, `sort_order`, `csrf` | `NavigationController::createItem()` / `updateItem()` | Redirect zpět na menu (volitelně s `item_id`) + flash. |
| Smazání položky | `admin.php?r=navigation&a=delete-item` | POST | `id`, `menu_id`, `csrf` | `NavigationController::deleteItem()` | Redirect zpět na menu + flash. |

## `settings`
| UI místo | Endpoint | Metoda | Klíčové parametry | Handler | Současná odpověď |
| --- | --- | --- | --- | --- | --- |
| Základní nastavení (`settings/index.php`) | `admin.php?r=settings&a=index` (implicitně `a=index`) | POST | `site_title`, `site_email`, `date_format`, `time_format`, `timezone`, `allow_registration`, `registration_auto_approve`, `site_url`, WebP nastavení (`webp_enabled`,`webp_compression`), `csrf` | `SettingsController::save()` | Redirect zpět na `admin.php?r=settings` + flash. |
| Mail nastavení (`settings/mail.php`) | `admin.php?r=settings&a=mail` (`intent=save`) | POST | `mail_driver`, `mail_from_email`, `mail_from_name`, `mail_signature`, SMTP pole (`mail_smtp_*`), `csrf` | `SettingsController::saveMail()` | Redirect zpět na mail stránku + flash. |
| Mail – testovací odeslání | `admin.php?r=settings&a=mail` (`intent=test`) | POST | `test_email`, `csrf` | `SettingsController::sendTestMail()` | Redirect zpět na mail stránku + flash (`success`/`danger`). |
| Permalinks (`settings/permalinks.php`) | `admin.php?r=settings&a=permalinks` | POST | `seo_urls_enabled`, `post_base`, `page_base`, `tag_base`, `category_base`, `csrf` | `SettingsController::savePermalinks()` | Redirect zpět na permalinks stránku + flash. |

## `themes`
| UI místo | Endpoint | Metoda | Klíčové parametry | Handler | Současná odpověď |
| --- | --- | --- | --- | --- | --- |
| Aktivace šablony (`themes/index.php`) | `admin.php?r=themes&a=activate` | POST | `slug`, `csrf` | `ThemesController::activate()` | Redirect zpět na seznam + flash. |
| Nahrání šablony | `admin.php?r=themes&a=upload` | POST (multipart) | `theme_zip`, `csrf` | `ThemesController::upload()` | Redirect zpět na seznam + flash. |

## `migrations`
| UI místo | Endpoint | Metoda | Klíčové parametry | Handler | Současná odpověď |
| --- | --- | --- | --- | --- | --- |
| Spuštění migrací (`migrations/index.php`) | `admin.php?r=migrations&a=run` | POST | `csrf` | `MigrationsController::run()` | Redirect zpět na `admin.php?r=migrations` + flash. |
| Rollback (`migrations/index.php`) | `admin.php?r=migrations&a=rollback` | POST | `csrf` | `MigrationsController::rollback()` | Redirect zpět + flash. |

## Další poznatky
- Většina `data-ajax` POSTů spoléhá na serverovou metodu `redirect()`, která v JSON odpovědi vrátí `redirect` + případný `flash` a na frontendu dojde k přesměrování celé stránky.
- `renderAdmin()` při AJAX požadavku vrací celé HTML šablony, což bude potřeba při migraci nahradit strukturovanými daty nebo specializovanými partialy.
- Ověření CSRF probíhá buď v `BaseAdminController::assertCsrf()` nebo přímo v akcích (`PostsController::autosave`).
