<?php
/** @var list<array<string,mixed>> $products */
/** @var array<string,mixed> $pagination */
/** @var array<string,mixed> $cart */
/** @var string $csrf */
/** @var \Cms\Admin\Utils\LinkGenerator $links */

$formatMoney = static function ($amount, $currency): string {
    $value = number_format((float)$amount, 2, ',', ' ');
    return $value . ' ' . htmlspecialchars((string)$currency, ENT_QUOTES, 'UTF-8');
};

$catalogUrl = $links->products();
$cartCount = isset($cart['count']) ? (int)$cart['count'] : 0;
?>
<section class="section section--catalog">
    <header class="section__header">
        <p class="section__eyebrow">Produkty</p>
        <h1 class="section__title">Naše nabídka</h1>
        <p class="section__lead">
            Vyberte si z aktuálně dostupných produktů. Objednávku dokončíte během několika kroků.
        </p>
        <p class="section__meta">
            <a class="button button--ghost" href="<?= htmlspecialchars($links->checkout(), ENT_QUOTES, 'UTF-8'); ?>">
                Pokračovat na pokladnu (<?= $cartCount; ?>)
            </a>
        </p>
    </header>

    <?php if ($products === []): ?>
        <div class="notice notice--info">
            <p>Zatím zde nemáme žádné produkty. Brzy ale něco přidáme.</p>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <?php
                    $productId = (int)($product['id'] ?? 0);
                    $productName = (string)($product['name'] ?? 'Produkt');
                    $productSlug = (string)($product['slug'] ?? '');
                    $productUrl = $productSlug !== '' ? $links->productDetail($productSlug) : $catalogUrl;
                    $description = trim((string)($product['short_description'] ?? ''));
                    if ($description === '') {
                        $description = 'Detailní informace najdete na stránce produktu.';
                    }
                ?>
                <article class="product-card">
                    <h2 class="product-card__title">
                        <a href="<?= htmlspecialchars($productUrl, ENT_QUOTES, 'UTF-8'); ?>">
                            <?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </h2>
                    <p class="product-card__price">
                        <?= $formatMoney($product['price'] ?? 0, $product['currency'] ?? ''); ?>
                    </p>
                    <p class="product-card__excerpt">
                        <?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    <div class="product-card__actions">
                        <form method="post" action="" class="product-card__form">
                            <input type="hidden" name="cart_action" value="add">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="product_id" value="<?= $productId; ?>">
                            <input type="hidden" name="quantity" value="1">
                            <button type="submit" class="button button--primary">
                                Přidat do košíku
                            </button>
                        </form>
                        <a class="button button--ghost" href="<?= htmlspecialchars($productUrl, ENT_QUOTES, 'UTF-8'); ?>">
                            Detail produktu
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php
        $totalPages = max(1, (int)($pagination['pages'] ?? 1));
        $currentPage = max(1, (int)($pagination['page'] ?? 1));
    ?>
    <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Stránkování katalogu">
            <?php
                $buildUrl = static function (int $page) use ($catalogUrl): string {
                    $base = $catalogUrl;
                    $separator = str_contains($base, '?') ? '&' : '?';
                    if ($page <= 1) {
                        return $base;
                    }
                    return $base . $separator . 'page=' . $page;
                };
            ?>
            <a class="pagination__link<?= $currentPage <= 1 ? ' is-disabled' : ''; ?>" href="<?= htmlspecialchars($buildUrl(max(1, $currentPage - 1)), ENT_QUOTES, 'UTF-8'); ?>">
                Předchozí
            </a>
            <span class="pagination__status">Stránka <?= $currentPage; ?> z <?= $totalPages; ?></span>
            <a class="pagination__link<?= $currentPage >= $totalPages ? ' is-disabled' : ''; ?>" href="<?= htmlspecialchars($buildUrl(min($totalPages, $currentPage + 1)), ENT_QUOTES, 'UTF-8'); ?>">
                Další
            </a>
        </nav>
    <?php endif; ?>
</section>
