<?php
/** @var array<string,mixed> $old */
/** @var array<string,list<string>> $errors */
/** @var bool $success */
/** @var string|null $message */
/** @var string|null $loginUrl */
/** @var string|null $csrf */
/** @var \Cms\Admin\Utils\LinkGenerator $links */

$old = is_array($old) ? $old : [];
$errors = is_array($errors) ? $errors : [];
$success = !empty($success);
$message = isset($message) && $message !== '' ? (string)$message : null;
$loginUrl = isset($loginUrl) && $loginUrl !== '' ? (string)$loginUrl : null;
$csrfToken = isset($csrf) && $csrf !== '' ? htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') : '';

$oldEmail = htmlspecialchars((string)($old['email'] ?? ''), ENT_QUOTES, 'UTF-8');
$action = htmlspecialchars($links->lost(), ENT_QUOTES, 'UTF-8');
?>
<section class="section section--auth section--lost">
    <header class="section__header">
        <p class="section__eyebrow">Obnova hesla</p>
        <h1 class="section__title">Zapomněli jste heslo?</h1>
        <p class="section__lead">Zadejte e-mail, na který vám zašleme instrukce k obnovení přístupu.</p>
    </header>

    <?php if ($message !== null): ?>
        <?php $noticeType = $success ? 'success' : (!empty($errors) ? 'warning' : 'info'); ?>
        <div class="notice notice--<?= htmlspecialchars($noticeType, ENT_QUOTES, 'UTF-8'); ?>">
            <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    <?php endif; ?>

    <div class="auth-card">
        <?php if ($success): ?>
            <p class="auth-card__text">Pokud e-mail existuje v naší databázi, zaslali jsme na něj odkaz pro nastavení nového hesla.</p>
            <?php if ($loginUrl): ?>
                <p class="auth-card__actions">
                    <a class="button" href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>">Zpět na přihlášení</a>
                </p>
            <?php endif; ?>
        <?php else: ?>
            <form method="post" action="<?= $action; ?>" class="auth-form" novalidate>
                <input type="hidden" name="csrf" value="<?= $csrfToken; ?>">
                <div class="auth-form__field<?= !empty($errors['email']) ? ' auth-form__field--error' : ''; ?>">
                    <label class="auth-form__label" for="lost-email">E-mailová adresa</label>
                    <input class="auth-form__input" type="email" id="lost-email" name="email" value="<?= $oldEmail; ?>" required>
                    <?php if (!empty($errors['email'])): ?>
                        <ul class="auth-form__errors">
                            <?php foreach ($errors['email'] as $error): ?>
                                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="auth-form__actions">
                    <button type="submit" class="button">Odeslat instrukce</button>
                    <?php if ($loginUrl): ?>
                        <p class="auth-form__hint">
                            Už máte heslo? <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>">Přihlaste se</a>.
                        </p>
                    <?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>
