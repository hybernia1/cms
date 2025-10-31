<?php
/** @var array<string,mixed> $cart */
/** @var array<string,mixed> $form */
/** @var array<string,list<string>> $errors */
/** @var array<string,mixed> $options */
/** @var string $csrf */
/** @var string $selected_shipping */
/** @var float $shipping_total */
/** @var \Cms\Admin\Utils\LinkGenerator $links */

$formatMoney = static function (float $amount, string $currency): string {
    $value = number_format($amount, 2, ',', ' ');
    return $value . ' ' . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8');
};

$cartItems = is_array($cart['items'] ?? null) ? $cart['items'] : [];
$currency = isset($cart['currency']) ? (string)$cart['currency'] : 'USD';
$subtotal = isset($cart['total']) ? (float)$cart['total'] : (float)($cart['subtotal'] ?? 0.0);
$shippingTotal = (float)$shipping_total;
$grandTotal = $subtotal + $shippingTotal;
$shippingOptions = is_array($options['shipping'] ?? null) ? $options['shipping'] : [];
$paymentOptions = is_array($options['payments'] ?? null) ? $options['payments'] : [];
$selectedShipping = (string)($selected_shipping ?? array_key_first($shippingOptions));
$selectedPayment = (string)($form['payment_method'] ?? array_key_first($paymentOptions));

$fieldHasError = static function (string $key) use ($errors): bool {
    return isset($errors[$key]) && $errors[$key] !== [];
};

$renderErrors = static function (string $key) use ($errors): void {
    if (!isset($errors[$key]) || $errors[$key] === []) {
        return;
    }
    echo '<div class="form-field__errors">';
    foreach ($errors[$key] as $message) {
        echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    echo '</div>';
};
?>
<section class="section section--checkout">
    <header class="section__header">
        <p class="section__eyebrow">Pokladna</p>
        <h1 class="section__title">Dokončete objednávku</h1>
        <p class="section__lead">Vyplňte kontaktní údaje, adresu a zvolte způsob dopravy i platby.</p>
    </header>

    <?php if (!empty($errors['general'])): ?>
        <div class="notice notice--danger">
            <?php foreach ($errors['general'] as $message): ?>
                <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="checkout-layout">
        <div class="checkout-layout__form">
            <form method="post" class="checkout-form">
                <input type="hidden" name="checkout_form" value="1">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">

                <fieldset class="checkout-form__section">
                    <legend>Kontaktní údaje</legend>
                    <div class="form-grid">
                        <label class="form-field<?= $fieldHasError('customer_first_name') ? ' is-invalid' : ''; ?>">
                            <span class="form-field__label">Jméno</span>
                            <input class="form-input" type="text" name="customer_first_name" value="<?= htmlspecialchars((string)($form['customer']['first_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php $renderErrors('customer_first_name'); ?>
                        </label>
                        <label class="form-field<?= $fieldHasError('customer_last_name') ? ' is-invalid' : ''; ?>">
                            <span class="form-field__label">Příjmení</span>
                            <input class="form-input" type="text" name="customer_last_name" value="<?= htmlspecialchars((string)($form['customer']['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php $renderErrors('customer_last_name'); ?>
                        </label>
                    </div>
                    <label class="form-field<?= $fieldHasError('customer_email') ? ' is-invalid' : ''; ?>">
                        <span class="form-field__label">E-mail</span>
                        <input class="form-input" type="email" name="customer_email" value="<?= htmlspecialchars((string)($form['customer']['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php $renderErrors('customer_email'); ?>
                    </label>
                    <label class="form-field">
                        <span class="form-field__label">Telefon</span>
                        <input class="form-input" type="tel" name="customer_phone" value="<?= htmlspecialchars((string)($form['customer']['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </label>
                </fieldset>

                <fieldset class="checkout-form__section">
                    <legend>Fakturační adresa</legend>
                    <div class="form-grid">
                        <label class="form-field<?= $fieldHasError('billing_first_name') ? ' is-invalid' : ''; ?>">
                            <span class="form-field__label">Jméno</span>
                            <input class="form-input" type="text" name="billing_first_name" value="<?= htmlspecialchars((string)($form['billing']['first_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php $renderErrors('billing_first_name'); ?>
                        </label>
                        <label class="form-field<?= $fieldHasError('billing_last_name') ? ' is-invalid' : ''; ?>">
                            <span class="form-field__label">Příjmení</span>
                            <input class="form-input" type="text" name="billing_last_name" value="<?= htmlspecialchars((string)($form['billing']['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php $renderErrors('billing_last_name'); ?>
                        </label>
                    </div>
                    <label class="form-field">
                        <span class="form-field__label">Společnost (nepovinné)</span>
                        <input class="form-input" type="text" name="billing_company" value="<?= htmlspecialchars((string)($form['billing']['company'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </label>
                    <label class="form-field<?= $fieldHasError('billing_line1') ? ' is-invalid' : ''; ?>">
                        <span class="form-field__label">Ulice a číslo</span>
                        <input class="form-input" type="text" name="billing_line1" value="<?= htmlspecialchars((string)($form['billing']['line1'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php $renderErrors('billing_line1'); ?>
                    </label>
                    <label class="form-field">
                        <span class="form-field__label">Doplňující řádek</span>
                        <input class="form-input" type="text" name="billing_line2" value="<?= htmlspecialchars((string)($form['billing']['line2'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </label>
                    <div class="form-grid">
                        <label class="form-field<?= $fieldHasError('billing_city') ? ' is-invalid' : ''; ?>">
                            <span class="form-field__label">Město</span>
                            <input class="form-input" type="text" name="billing_city" value="<?= htmlspecialchars((string)($form['billing']['city'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php $renderErrors('billing_city'); ?>
                        </label>
                        <label class="form-field">
                            <span class="form-field__label">Kraj</span>
                            <input class="form-input" type="text" name="billing_state" value="<?= htmlspecialchars((string)($form['billing']['state'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                    </div>
                    <div class="form-grid">
                        <label class="form-field<?= $fieldHasError('billing_postal_code') ? ' is-invalid' : ''; ?>">
                            <span class="form-field__label">PSČ</span>
                            <input class="form-input" type="text" name="billing_postal_code" value="<?= htmlspecialchars((string)($form['billing']['postal_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php $renderErrors('billing_postal_code'); ?>
                        </label>
                        <label class="form-field<?= $fieldHasError('billing_country') ? ' is-invalid' : ''; ?>">
                            <span class="form-field__label">Země</span>
                            <input class="form-input" type="text" name="billing_country" value="<?= htmlspecialchars((string)($form['billing']['country'] ?? 'CZ'), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php $renderErrors('billing_country'); ?>
                        </label>
                    </div>
                </fieldset>

                <fieldset class="checkout-form__section">
                    <legend>Doručovací adresa</legend>
                    <label class="form-checkbox">
                        <input type="checkbox" name="shipping_same" value="1"<?= !empty($form['shipping_same']) ? ' checked' : ''; ?>>
                        <span>Použít stejnou adresu jako fakturační</span>
                    </label>

                    <div class="form-grid">
                        <label class="form-field<?= $fieldHasError('shipping_first_name') ? ' is-invalid' : ''; ?>">
                            <span class="form-field__label">Jméno</span>
                            <input class="form-input" type="text" name="shipping_first_name" value="<?= htmlspecialchars((string)($form['shipping']['first_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php $renderErrors('shipping_first_name'); ?>
                        </label>
                        <label class="form-field<?= $fieldHasError('shipping_last_name') ? ' is-invalid' : ''; ?>">
                            <span class="form-field__label">Příjmení</span>
                            <input class="form-input" type="text" name="shipping_last_name" value="<?= htmlspecialchars((string)($form['shipping']['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php $renderErrors('shipping_last_name'); ?>
                        </label>
                    </div>
                    <label class="form-field">
                        <span class="form-field__label">Společnost</span>
                        <input class="form-input" type="text" name="shipping_company" value="<?= htmlspecialchars((string)($form['shipping']['company'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </label>
                    <label class="form-field<?= $fieldHasError('shipping_line1') ? ' is-invalid' : ''; ?>">
                        <span class="form-field__label">Ulice a číslo</span>
                        <input class="form-input" type="text" name="shipping_line1" value="<?= htmlspecialchars((string)($form['shipping']['line1'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php $renderErrors('shipping_line1'); ?>
                    </label>
                    <label class="form-field">
                        <span class="form-field__label">Doplňující řádek</span>
                        <input class="form-input" type="text" name="shipping_line2" value="<?= htmlspecialchars((string)($form['shipping']['line2'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </label>
                    <div class="form-grid">
                        <label class="form-field<?= $fieldHasError('shipping_city') ? ' is-invalid' : ''; ?>">
                            <span class="form-field__label">Město</span>
                            <input class="form-input" type="text" name="shipping_city" value="<?= htmlspecialchars((string)($form['shipping']['city'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php $renderErrors('shipping_city'); ?>
                        </label>
                        <label class="form-field">
                            <span class="form-field__label">Kraj</span>
                            <input class="form-input" type="text" name="shipping_state" value="<?= htmlspecialchars((string)($form['shipping']['state'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                    </div>
                    <div class="form-grid">
                        <label class="form-field<?= $fieldHasError('shipping_postal_code') ? ' is-invalid' : ''; ?>">
                            <span class="form-field__label">PSČ</span>
                            <input class="form-input" type="text" name="shipping_postal_code" value="<?= htmlspecialchars((string)($form['shipping']['postal_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php $renderErrors('shipping_postal_code'); ?>
                        </label>
                        <label class="form-field<?= $fieldHasError('shipping_country') ? ' is-invalid' : ''; ?>">
                            <span class="form-field__label">Země</span>
                            <input class="form-input" type="text" name="shipping_country" value="<?= htmlspecialchars((string)($form['shipping']['country'] ?? 'CZ'), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php $renderErrors('shipping_country'); ?>
                        </label>
                    </div>
                </fieldset>

                <fieldset class="checkout-form__section">
                    <legend>Doprava</legend>
                    <div class="form-list">
                        <?php foreach ($shippingOptions as $code => $option): ?>
                            <?php
                                $label = (string)($option['label'] ?? ucfirst($code));
                                $amount = (float)($option['amount'] ?? 0.0);
                            ?>
                            <label class="form-radio<?= $selectedShipping === $code ? ' is-active' : ''; ?>">
                                <input type="radio" name="shipping_method" value="<?= htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8'); ?>"<?= $selectedShipping === $code ? ' checked' : ''; ?>>
                                <span>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                    <small><?= $formatMoney($amount, $currency); ?></small>
                                    <?php if (!empty($option['description'])): ?>
                                        <em><?= htmlspecialchars((string)$option['description'], ENT_QUOTES, 'UTF-8'); ?></em>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <fieldset class="checkout-form__section">
                    <legend>Platba</legend>
                    <div class="form-list">
                        <?php foreach ($paymentOptions as $code => $option): ?>
                            <?php $label = (string)($option['label'] ?? ucfirst($code)); ?>
                            <label class="form-radio<?= $selectedPayment === $code ? ' is-active' : ''; ?>">
                                <input type="radio" name="payment_method" value="<?= htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8'); ?>"<?= $selectedPayment === $code ? ' checked' : ''; ?>>
                                <span>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if (!empty($option['description'])): ?>
                                        <em><?= htmlspecialchars((string)$option['description'], ENT_QUOTES, 'UTF-8'); ?></em>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <fieldset class="checkout-form__section">
                    <legend>Další možnosti</legend>
                    <label class="form-checkbox">
                        <input type="checkbox" name="marketing_opt_in" value="1"<?= !empty($form['marketing_opt_in']) ? ' checked' : ''; ?>>
                        <span>Chci dostávat novinky a slevy e-mailem</span>
                    </label>
                    <label class="form-checkbox">
                        <input type="checkbox" name="create_account" value="1"<?= !empty($form['create_account']) ? ' checked' : ''; ?>>
                        <span>Vytvořit účet pro budoucí nákupy</span>
                    </label>
                    <div class="form-grid form-grid--account">
                        <label class="form-field<?= $fieldHasError('password') ? ' is-invalid' : ''; ?>">
                            <span class="form-field__label">Heslo</span>
                            <input class="form-input" type="password" name="password" value="">
                            <?php $renderErrors('password'); ?>
                        </label>
                        <label class="form-field<?= $fieldHasError('password_confirmation') ? ' is-invalid' : ''; ?>">
                            <span class="form-field__label">Potvrzení hesla</span>
                            <input class="form-input" type="password" name="password_confirmation" value="">
                            <?php $renderErrors('password_confirmation'); ?>
                        </label>
                    </div>
                    <label class="form-field">
                        <span class="form-field__label">Poznámka k objednávce</span>
                        <textarea class="form-textarea" name="order_notes" rows="4"><?= htmlspecialchars((string)($form['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </label>
                </fieldset>

                <div class="checkout-form__actions">
                    <button type="submit" class="button button--primary button--large">Odeslat objednávku</button>
                </div>
            </form>
        </div>

        <aside class="checkout-layout__summary">
            <div class="checkout-summary">
                <h2>Souhrn objednávky</h2>
                <?php if ($cartItems === []): ?>
                    <p>Váš košík je prázdný. <a href="<?= htmlspecialchars($links->products(), ENT_QUOTES, 'UTF-8'); ?>">Pokračovat v nákupu</a>.</p>
                <?php else: ?>
                    <ul class="checkout-summary__items">
                        <?php foreach ($cartItems as $item): ?>
                            <li class="checkout-summary__item">
                                <span class="checkout-summary__item-name">
                                    <?= htmlspecialchars((string)($item['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                    <small>× <?= (int)($item['quantity'] ?? 1); ?></small>
                                </span>
                                <span class="checkout-summary__item-total">
                                    <?= $formatMoney((float)($item['subtotal'] ?? 0.0), (string)($item['currency'] ?? $currency)); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <dl class="checkout-summary__totals">
                        <div class="checkout-summary__line">
                            <dt>Mezisoučet</dt>
                            <dd><?= $formatMoney($subtotal, $currency); ?></dd>
                        </div>
                        <div class="checkout-summary__line">
                            <dt>Doprava</dt>
                            <dd><?= $formatMoney($shippingTotal, $currency); ?></dd>
                        </div>
                        <div class="checkout-summary__line checkout-summary__line--grand">
                            <dt>Celkem</dt>
                            <dd><?= $formatMoney($grandTotal, $currency); ?></dd>
                        </div>
                    </dl>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</section>
