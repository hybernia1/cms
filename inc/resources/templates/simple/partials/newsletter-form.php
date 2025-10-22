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
$noticeClass = $success ? 'notice-success' : (($emailErrors !== [] || $generalErrors !== []) ? 'notice-warning' : 'notice');
?>
<section class="newsletter-box">
    <h2>Přihlášení k newsletteru</h2>

    <?php if ($message !== '' || $generalErrors !== []): ?>
        <div class="<?= htmlspecialchars($noticeClass, ENT_QUOTES, 'UTF-8'); ?>">
            <?php if ($message !== ''): ?>
                <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <?php if ($generalErrors !== []): ?>
                <p><?= htmlspecialchars((string)$generalErrors[0], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($allowForm): ?>
        <form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>" class="newsletter-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="source" value="<?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="newsletter_form" value="1">

            <label for="newsletter-email">Váš e-mail</label>
            <input
                type="email"
                id="newsletter-email"
                name="email"
                value="<?= htmlspecialchars($emailValue, ENT_QUOTES, 'UTF-8'); ?>"
                placeholder="např. jana@example.cz"
                required
            >
            <?php if ($emailErrors !== []): ?>
                <p class="newsletter-form__error"><?= htmlspecialchars((string)$emailErrors[0], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <button type="submit">Odebírat</button>
        </form>
    <?php endif; ?>
</section>
