<?php
/** @var array<string,mixed>|null $user */
/** @var array<string,mixed> $old */
/** @var array<string,list<string>> $errors */
/** @var bool $success */
/** @var bool $allowForm */
/** @var string|null $message */
/** @var string|null $loginUrl */
/** @var string|null $profileUrl */
/** @var string|null $csrf */
/** @var \Cms\Admin\Utils\LinkGenerator $links */

$user = is_array($user ?? null) ? $user : [];
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$success = !empty($success);
$allowForm = isset($allowForm) ? (bool)$allowForm : true;
$message = isset($message) && $message !== '' ? (string)$message : null;
$loginUrl = isset($loginUrl) && $loginUrl !== '' ? (string)$loginUrl : null;
$profileUrl = isset($profileUrl) && $profileUrl !== '' ? (string)$profileUrl : null;
$csrfToken = isset($csrf) && $csrf !== '' ? htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') : '';

$currentAvatar = isset($user['avatar_url']) ? trim((string)$user['avatar_url']) : '';
$oldName = htmlspecialchars((string)($old['name'] ?? ($user['name'] ?? '')), ENT_QUOTES, 'UTF-8');
$oldWebsite = htmlspecialchars((string)($old['website'] ?? ($user['website_url'] ?? '')), ENT_QUOTES, 'UTF-8');
$oldBio = htmlspecialchars((string)($old['bio'] ?? ($user['bio'] ?? '')), ENT_QUOTES, 'UTF-8');
$action = htmlspecialchars($links->account(), ENT_QUOTES, 'UTF-8');
?>
<section class="section section--auth">
    <header class="section__header">
        <p class="section__eyebrow">Profil</p>
        <h1 class="section__title">Upravit profil</h1>
        <p class="section__lead">
            Aktualizujte své zobrazované jméno, veřejný web a avatar pro zobrazení na stránce autora.
        </p>
    </header>

    <?php if ($message !== null): ?>
        <?php $noticeType = $success ? 'success' : ($allowForm ? 'info' : 'danger'); ?>
        <div class="notice notice--<?= htmlspecialchars($noticeType, ENT_QUOTES, 'UTF-8'); ?>">
            <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    <?php endif; ?>

    <div class="auth-card">
        <?php if (!$allowForm): ?>
            <p class="auth-card__text">Pro úpravu profilu se prosím přihlaste.</p>
            <?php if ($loginUrl): ?>
                <p class="auth-card__actions">
                    <a class="button" href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>">Přejít na přihlášení</a>
                </p>
            <?php endif; ?>
        <?php else: ?>
            <form method="post" action="<?= $action; ?>" class="auth-form auth-form--profile" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="csrf" value="<?= $csrfToken; ?>">

                <div class="auth-form__field<?= !empty($errors['name']) ? ' auth-form__field--error' : ''; ?>">
                    <label class="auth-form__label" for="account-name">Jméno</label>
                    <input class="auth-form__input" type="text" id="account-name" name="name" value="<?= $oldName; ?>" required>
                    <?php if (!empty($errors['name'])): ?>
                        <ul class="auth-form__errors">
                            <?php foreach ($errors['name'] as $error): ?>
                                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="auth-form__field<?= !empty($errors['website']) ? ' auth-form__field--error' : ''; ?>">
                    <label class="auth-form__label" for="account-website">Webová stránka</label>
                    <input class="auth-form__input" type="url" id="account-website" name="website" value="<?= $oldWebsite; ?>" placeholder="https://example.com">
                    <?php if (!empty($errors['website'])): ?>
                        <ul class="auth-form__errors">
                            <?php foreach ($errors['website'] as $error): ?>
                                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="auth-form__field<?= !empty($errors['avatar']) ? ' auth-form__field--error' : ''; ?>">
                    <label class="auth-form__label" for="account-avatar">Avatar</label>
                    <input class="auth-form__input" type="file" id="account-avatar" name="avatar" accept="image/jpeg,image/png">
                    <?php if ($currentAvatar !== ''): ?>
                        <div class="auth-form__note">
                            <img class="auth-form__avatar-preview" src="<?= htmlspecialchars($currentAvatar, ENT_QUOTES, 'UTF-8'); ?>" alt="Aktuální avatar" width="64" height="64">
                        </div>
                    <?php endif; ?>
                    <p class="auth-form__hint">Podporované formáty: JPEG nebo PNG. Velikost souboru maximálně 5&nbsp;MB.</p>
                    <?php if (!empty($errors['avatar'])): ?>
                        <ul class="auth-form__errors">
                            <?php foreach ($errors['avatar'] as $error): ?>
                                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="auth-form__field<?= !empty($errors['bio']) ? ' auth-form__field--error' : ''; ?>">
                    <label class="auth-form__label" for="account-bio">Krátké představení</label>
                    <textarea class="auth-form__textarea" id="account-bio" name="bio" rows="5" maxlength="600" placeholder="Napište několik vět o sobě."><?= $oldBio; ?></textarea>
                    <p class="auth-form__hint">Maximálně 600 znaků. Text se zobrazí na vašem veřejném profilu.</p>
                    <?php if (!empty($errors['bio'])): ?>
                        <ul class="auth-form__errors">
                            <?php foreach ($errors['bio'] as $error): ?>
                                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="auth-form__actions">
                    <button type="submit" class="button">Uložit změny</button>
                    <?php if ($profileUrl): ?>
                        <p class="auth-form__hint">
                            <a href="<?= htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Zobrazit veřejný profil</a>
                        </p>
                    <?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>
