<?php
/** @var array<string,mixed> $order */
/** @var array<string,mixed> $customer */
/** @var array<string,mixed> $cart */
/** @var array<string,mixed> $shipping */
/** @var array<string,mixed> $payment */
/** @var \Cms\Admin\Utils\LinkGenerator $links */

$formatMoney = static function (float $amount, string $currency): string {
    $value = number_format($amount, 2, ',', ' ');
    return $value . ' ' . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8');
};

$orderNumber = (string)($order['order_number'] ?? '');
$orderCurrency = (string)($order['currency'] ?? 'USD');
$subtotal = (float)($order['subtotal'] ?? 0.0);
$shippingTotal = (float)($order['shipping_total'] ?? 0.0);
$total = (float)($order['total'] ?? ($subtotal + $shippingTotal));
?>
<section class="section section--checkout-complete">
    <header class="section__header">
        <p class="section__eyebrow">Objednávka dokončena</p>
        <h1 class="section__title">Děkujeme za nákup!</h1>
        <p class="section__lead">Vaše objednávka byla úspěšně zaznamenána. Níže najdete její souhrn.</p>
    </header>

    <div class="checkout-complete">
        <div class="checkout-complete__summary">
            <h2>Souhrn objednávky</h2>
            <?php if ($orderNumber !== ''): ?>
                <p class="checkout-complete__order-number">Číslo objednávky: <strong><?= htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8'); ?></strong></p>
            <?php endif; ?>
            <dl class="checkout-complete__totals">
                <div class="checkout-complete__line">
                    <dt>Mezisoučet</dt>
                    <dd><?= $formatMoney($subtotal, $orderCurrency); ?></dd>
                </div>
                <div class="checkout-complete__line">
                    <dt>Doprava</dt>
                    <dd><?= $formatMoney($shippingTotal, $orderCurrency); ?></dd>
                </div>
                <div class="checkout-complete__line checkout-complete__line--grand">
                    <dt>Celkem</dt>
                    <dd><?= $formatMoney($total, $orderCurrency); ?></dd>
                </div>
            </dl>
            <p class="checkout-complete__next">
                Potvrzení o objednávce jsme poslali na e-mail <?= htmlspecialchars((string)($customer['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>.
            </p>
            <p class="checkout-complete__actions">
                <a class="button button--primary" href="<?= htmlspecialchars($links->products(), ENT_QUOTES, 'UTF-8'); ?>">Pokračovat v nákupu</a>
            </p>
        </div>

        <aside class="checkout-complete__details">
            <section>
                <h3>Kontaktní údaje</h3>
                <ul>
                    <li><?= htmlspecialchars((string)($customer['first_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> <?= htmlspecialchars((string)($customer['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></li>
                    <li><?= htmlspecialchars((string)($customer['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php if (!empty($customer['phone'])): ?>
                        <li><?= htmlspecialchars((string)$customer['phone'], ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endif; ?>
                </ul>
            </section>

            <section>
                <h3>Doprava a platba</h3>
                <ul>
                    <li>Doprava: <?= htmlspecialchars((string)($shipping['label'] ?? 'Zvoleno'), ENT_QUOTES, 'UTF-8'); ?></li>
                    <li>Platba: <?= htmlspecialchars((string)($payment['label'] ?? 'Zvoleno'), ENT_QUOTES, 'UTF-8'); ?></li>
                </ul>
            </section>

            <?php if (!empty($cart['items'])): ?>
                <section>
                    <h3>Položky objednávky</h3>
                    <ul class="checkout-complete__items">
                        <?php foreach ($cart['items'] as $item): ?>
                            <li>
                                <span><?= htmlspecialchars((string)($item['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                <small>× <?= (int)($item['quantity'] ?? 1); ?></small>
                                <span class="checkout-complete__item-price">
                                    <?= $formatMoney((float)($item['subtotal'] ?? 0.0), (string)($item['currency'] ?? $orderCurrency)); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </aside>
    </div>
</section>
