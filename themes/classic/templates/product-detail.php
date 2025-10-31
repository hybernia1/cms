<?php
/** @var array<string,mixed> $product */
/** @var list<array<string,mixed>> $variants */
/** @var string $csrf */
/** @var array<string,mixed> $cart */
/** @var \Cms\Admin\Utils\LinkGenerator $links */

$formatMoney = static function ($amount, $currency): string {
    $value = number_format((float)$amount, 2, ',', '\u00a0');
    return $value . ' ' . htmlspecialchars((string)$currency, ENT_QUOTES, 'UTF-8');
};

$productId = (int)($product['id'] ?? 0);
$productName = (string)($product['name'] ?? 'Produkt');
$productPrice = (float)($product['price'] ?? 0);
$productCurrency = (string)($product['currency'] ?? '');
$productCategories = is_array($product['categories'] ?? null) ? $product['categories'] : [];
$productDescription = trim((string)($product['description'] ?? ''));
$shortDescription = trim((string)($product['short_description'] ?? ''));
$hasVariants = $variants !== [];
$defaultVariantId = $hasVariants ? (int)($variants[0]['id'] ?? 0) : null;
$cartCount = isset($cart['count']) ? (int)$cart['count'] : 0;
?>
<section class="section section--product-detail">
    <header class="section__header section__header--product">
        <p class="section__eyebrow">Produkt</p>
        <h1 class="section__title"><?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="section__lead">
            <?= htmlspecialchars($shortDescription !== '' ? $shortDescription : 'Podívejte se na detaily produktu a přidejte si ho do košíku.', ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <p class="section__meta">
            <a class="button button--ghost" href="<?= htmlspecialchars($links->products(), ENT_QUOTES, 'UTF-8'); ?>">Zpět do katalogu</a>
            <a class="button button--ghost" href="<?= htmlspecialchars($links->checkout(), ENT_QUOTES, 'UTF-8'); ?>">Košík (<?= $cartCount; ?>)</a>
        </p>
    </header>

    <div class="product-detail">
        <div class="product-detail__content">
            <div class="product-detail__price">
                <strong>Cena</strong>
                <span><?= $formatMoney($productPrice, $productCurrency); ?></span>
            </div>

            <form method="post" action="" class="product-detail__form">
                <input type="hidden" name="cart_action" value="add">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="product_id" value="<?= $productId; ?>">

                <?php if ($hasVariants): ?>
                    <label class="form-field">
                        <span class="form-field__label">Varianta</span>
                        <select name="variant_id" class="form-select">
                            <?php foreach ($variants as $variant): ?>
                                <?php
                                    $variantId = (int)($variant['id'] ?? 0);
                                    $variantName = (string)($variant['name'] ?? ('Varianta #' . $variantId));
                                    $variantPrice = isset($variant['price']) ? (float)$variant['price'] : $productPrice;
                                    $variantCurrency = (string)($variant['currency'] ?? $productCurrency);
                                    $label = $variantName . ' — ' . number_format($variantPrice, 2, ',', '\u00a0') . ' ' . $variantCurrency;
                                ?>
                                <option value="<?= $variantId; ?>"<?= $defaultVariantId === $variantId ? ' selected' : ''; ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php elseif ($defaultVariantId !== null): ?>
                    <input type="hidden" name="variant_id" value="<?= $defaultVariantId; ?>">
                <?php endif; ?>

                <label class="form-field form-field--inline">
                    <span class="form-field__label">Množství</span>
                    <input class="form-input" type="number" name="quantity" value="1" min="1" step="1">
                </label>

                <button type="submit" class="button button--primary button--large">Přidat do košíku</button>
            </form>

            <?php if ($productCategories !== []): ?>
                <p class="product-detail__categories">
                    <strong>Kategorie:</strong>
                    <?php foreach ($productCategories as $index => $category): ?>
                        <?php if ($index > 0): ?>, <?php endif; ?>
                        <span><?= htmlspecialchars((string)($category['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>

            <?php if ($hasVariants): ?>
                <section class="product-detail__variants">
                    <h2>Varianty</h2>
                    <ul>
                        <?php foreach ($variants as $variant): ?>
                            <li>
                                <strong><?= htmlspecialchars((string)($variant['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span> — <?= $formatMoney($variant['price'] ?? $productPrice, $variant['currency'] ?? $productCurrency); ?></span>
                                <?php
                                    $attributes = is_array($variant['attributes'] ?? null) ? $variant['attributes'] : [];
                                    if ($attributes !== []):
                                ?>
                                    <ul class="product-detail__variant-attributes">
                                        <?php foreach ($attributes as $attribute): ?>
                                            <li>
                                                <?= htmlspecialchars((string)($attribute['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>:
                                                <?= htmlspecialchars((string)($attribute['value'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php if ($productDescription !== ''): ?>
                <section class="product-detail__description">
                    <h2>Popis</h2>
                    <div class="product-detail__description-content">
                        <?= $productDescription; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>
</section>
