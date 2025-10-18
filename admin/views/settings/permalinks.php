<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array{seo_urls:bool,post_base:string,page_base:string,tag_base:string,category_base:string} $permalinks */
/** @var array{seo_urls:bool,post_base:string,page_base:string,tag_base:string,category_base:string} $defaults */
/** @var string $csrf */

$this->render('layouts/base', compact('pageTitle','nav','currentUser','flash'), function () use ($permalinks,$defaults,$csrf) {
    $h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $seoEnabled = !empty($permalinks['seo_urls']);

    $settingsObj = new \Cms\Settings\CmsSettings();
    $prettyUrls = new \Cms\Utils\LinkGenerator(true, $settingsObj);
    $fallbackUrls = new \Cms\Utils\LinkGenerator(false, $settingsObj);

    $exampleSlug = 'ukazkovy-prispevek';
    $pageSlug = 'kontakt';
    $tagSlug = 'zajimavosti';
    $categorySlug = 'novinky';

    $postPretty = $prettyUrls->post($exampleSlug);
    $postFallback = $fallbackUrls->post($exampleSlug);
    $pagePretty = $prettyUrls->page($pageSlug);
    $pageFallback = $fallbackUrls->page($pageSlug);
    $tagPretty = $prettyUrls->tag($tagSlug);
    $tagFallback = $fallbackUrls->tag($tagSlug);
    $categoryPretty = $prettyUrls->category($categorySlug);
    $categoryFallback = $fallbackUrls->category($categorySlug);

    $currentPost = $seoEnabled ? $postPretty : $postFallback;
    $currentPage = $seoEnabled ? $pagePretty : $pageFallback;
    $currentTag = $seoEnabled ? $tagPretty : $tagFallback;
    $currentCategory = $seoEnabled ? $categoryPretty : $categoryFallback;
?>
  <form class="card" method="post" action="admin.php?r=settings&a=permalinks" data-ajax>
    <div class="card-body">
      <div class="mb-4">
        <h2 class="h6 text-uppercase text-secondary fw-semibold mb-3">SEO URL</h2>
        <p class="text-secondary small mb-3">
          Trvalé odkazy určují, jak budou vypadat veřejné adresy pro různé typy obsahu. Pokud váš server nepodporuje
          přepisování URL (např. nemáte aktivní mod_rewrite), můžete přepnout na základní variantu s parametry.
        </p>
        <input type="hidden" name="seo_urls_enabled" value="0">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="seo_urls_enabled" name="seo_urls_enabled" value="1"<?= $seoEnabled ? ' checked' : '' ?>>
          <label class="form-check-label" for="seo_urls_enabled">Povolit SEO URL</label>
        </div>
        <div class="form-text">
          Přátelská adresa: <code><?= $h($postPretty) ?></code><br>
          Adresa s parametry: <code><?= $h($postFallback) ?></code>
        </div>
      </div>

      <div class="mb-4 border-top pt-4">
        <h2 class="h6 text-uppercase text-secondary fw-semibold mb-3">Základní slugs</h2>
        <div class="row g-3">
          <div class="col-md-6 col-lg-3">
            <label class="form-label" for="post_base">Příspěvky</label>
            <input class="form-control" id="post_base" name="post_base" value="<?= $h($permalinks['post_base']) ?>" pattern="[a-z0-9\-]+" required>
            <div class="form-text">Výchozí: <?= $h($defaults['post_base']) ?></div>
          </div>
          <div class="col-md-6 col-lg-3">
            <label class="form-label" for="page_base">Stránky</label>
            <input class="form-control" id="page_base" name="page_base" value="<?= $h($permalinks['page_base']) ?>" pattern="[a-z0-9\-]+" required>
            <div class="form-text">Výchozí: <?= $h($defaults['page_base']) ?></div>
          </div>
          <div class="col-md-6 col-lg-3">
            <label class="form-label" for="category_base">Kategorie</label>
            <input class="form-control" id="category_base" name="category_base" value="<?= $h($permalinks['category_base']) ?>" pattern="[a-z0-9\-]+" required>
            <div class="form-text">Výchozí: <?= $h($defaults['category_base']) ?></div>
          </div>
          <div class="col-md-6 col-lg-3">
            <label class="form-label" for="tag_base">Štítky</label>
            <input class="form-control" id="tag_base" name="tag_base" value="<?= $h($permalinks['tag_base']) ?>" pattern="[a-z0-9\-]+" required>
            <div class="form-text">Výchozí: <?= $h($defaults['tag_base']) ?></div>
          </div>
        </div>
        <div class="form-text mt-2">Používejte pouze malá písmena, čísla a pomlčky. Změna adresy může ovlivnit SEO – po úpravě aktualizujte odkazy v menu i obsahu.</div>
        <div class="alert alert-secondary mt-3 mb-0">
          <strong>Příklady:</strong>
          <ul class="mb-0 ps-3">
            <li>Příspěvek: <code><?= $h($currentPost) ?></code></li>
            <li>Stránka: <code><?= $h($currentPage) ?></code></li>
            <li>Kategorie: <code><?= $h($currentCategory) ?></code></li>
            <li>Štítek: <code><?= $h($currentTag) ?></code></li>
          </ul>
        </div>
      </div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
      <span class="text-secondary small">Změny se projeví po uložení.</span>
      <button class="btn btn-primary" type="submit">
        <i class="bi bi-check2-circle me-1"></i>Uložit nastavení
      </button>
    </div>
  </form>
<?php
});
