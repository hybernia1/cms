<?php
/** @var array<string,mixed>|null $newsletterForm */

$form = is_array($newsletterForm ?? null) ? $newsletterForm : [];
$action = isset($form['action']) ? (string)$form['action'] : '';
$csrf = isset($form['csrf']) ? (string)$form['csrf'] : '';
$redirect = isset($form['redirect']) ? (string)$form['redirect'] : '';
$source = isset($form['source']) ? (string)$form['source'] : '';
$success = !empty($form['success']);
$allowForm = !array_key_exists('allowForm', $form) || !empty($form['allowForm']);
$message = isset($form['message']) ? trim((string)$form['message']) : '';
$errors = is_array($form['errors'] ?? null) ? $form['errors'] : [];
$emailErrors = is_array($errors['email'] ?? null) ? $errors['email'] : [];
$generalErrors = is_array($errors['general'] ?? null) ? $errors['general'] : [];
$old = is_array($form['old'] ?? null) ? $form['old'] : [];
$emailValue = isset($old['email']) ? (string)$old['email'] : '';
$noticeType = $success ? 'success' : (($emailErrors !== [] || $generalErrors !== []) ? 'warning' : 'info');
?>
<section class="widget widget--newsletter">
    <header class="widget__header">
        <p class="widget__eyebrow">Newsletter</p>
        <h2 class="widget__title">Zůstaňte v obraze</h2>
    </header>

    <?php if ($message !== '' || $generalErrors !== []): ?>
        <div class="notice notice--<?= htmlspecialchars($noticeType, ENT_QUOTES, 'UTF-8'); ?>">
            <?php if ($message !== ''): ?>
                <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <?php if ($generalErrors !== []): ?>
                <p><?= htmlspecialchars((string)$generalErrors[0], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($allowForm): ?>
        <form class="newsletter-form" method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="source" value="<?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="newsletter_form" value="1">

            <label class="newsletter-form__label" for="newsletter-email">Váš e-mail</label>
            <div class="newsletter-form__controls">
                <input
                    class="newsletter-form__input<?= $emailErrors !== [] ? ' newsletter-form__input--error' : ''; ?>"
                    type="email"
                    id="newsletter-email"
                    name="email"
                    value="<?= htmlspecialchars($emailValue, ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="např. jana@example.cz"
                    required
                >
                <button class="newsletter-form__button" type="submit">Odebírat</button>
            </div>
            <?php if ($emailErrors !== []): ?>
                <p class="newsletter-form__error"><?= htmlspecialchars((string)$emailErrors[0], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <p class="newsletter-form__note">Žádný spam. Kdykoliv se můžete odhlásit jedním klikem.</p>
        </form>
    <?php endif; ?>
</section>
