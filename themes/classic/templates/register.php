<?php
/** @var array<string,mixed> $old */
/** @var array<string,list<string>> $errors */
/** @var bool $success */
/** @var bool $allowed */
/** @var bool|null $autoApprove */
/** @var string|null $message */
/** @var string|null $loginUrl */
/** @var \Cms\Admin\Utils\LinkGenerator $links */

$old = is_array($old) ? $old : [];
$errors = is_array($errors) ? $errors : [];
$success = !empty($success);
$allowed = isset($allowed) ? (bool)$allowed : true;
$autoApprove = isset($autoApprove) ? (bool)$autoApprove : null;
$message = isset($message) && $message !== '' ? (string)$message : null;
$loginUrl = isset($loginUrl) && $loginUrl !== '' ? (string)$loginUrl : null;

$oldName = htmlspecialchars((string)($old['name'] ?? ''), ENT_QUOTES, 'UTF-8');
$oldEmail = htmlspecialchars((string)($old['email'] ?? ''), ENT_QUOTES, 'UTF-8');
$action = htmlspecialchars($links->register(), ENT_QUOTES, 'UTF-8');
?>
<section class="section section--auth">
    <header class="section__header">
        <p class="section__eyebrow">Registrace</p>
        <h1 class="section__title">Vytvořte si účet</h1>
        <p class="section__lead">
            Založením účtu získáte přístup k obsahu vyhrazenému pro registrované uživatele.
            <?php if ($autoApprove === false): ?>
                Po odeslání vyčkejte na schválení administrátorem.
            <?php endif; ?>
        </p>
    </header>

    <?php if ($message !== null): ?>
        <?php
            $noticeType = $success ? 'success' : ($allowed ? 'info' : 'danger');
        ?>
        <div class="notice notice--<?= htmlspecialchars($noticeType, ENT_QUOTES, 'UTF-8'); ?>">
            <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    <?php endif; ?>

    <div class="auth-card">
        <?php if ($success): ?>
            <p class="auth-card__text">Registrace proběhla v pořádku.</p>
            <?php if ($loginUrl): ?>
                <p class="auth-card__actions">
                    <a class="button" href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>">Přejít na přihlášení</a>
                </p>
            <?php endif; ?>
        <?php elseif (!$allowed): ?>
            <p class="auth-card__text">Registrace je momentálně vypnutá. Zkuste to prosím později.</p>
        <?php else: ?>
            <form method="post" action="<?= $action; ?>" class="auth-form" novalidate>
                <div class="auth-form__field<?= !empty($errors['name']) ? ' auth-form__field--error' : ''; ?>">
                    <label class="auth-form__label" for="reg-name">Jméno</label>
                    <input class="auth-form__input" type="text" id="reg-name" name="name" value="<?= $oldName; ?>" required>
                    <?php if (!empty($errors['name'])): ?>
                        <ul class="auth-form__errors">
                            <?php foreach ($errors['name'] as $error): ?>
                                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="auth-form__field<?= !empty($errors['email']) ? ' auth-form__field--error' : ''; ?>">
                    <label class="auth-form__label" for="reg-email">E-mail</label>
                    <input class="auth-form__input" type="email" id="reg-email" name="email" value="<?= $oldEmail; ?>" required>
                    <?php if (!empty($errors['email'])): ?>
                        <ul class="auth-form__errors">
                            <?php foreach ($errors['email'] as $error): ?>
                                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="auth-form__field<?= !empty($errors['password']) ? ' auth-form__field--error' : ''; ?>">
                    <label class="auth-form__label" for="reg-password">Heslo</label>
                    <input class="auth-form__input" type="password" id="reg-password" name="password" required minlength="8">
                    <?php if (!empty($errors['password'])): ?>
                        <ul class="auth-form__errors">
                            <?php foreach ($errors['password'] as $error): ?>
                                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="auth-form__field<?= !empty($errors['password_confirm']) ? ' auth-form__field--error' : ''; ?>">
                    <label class="auth-form__label" for="reg-password-confirm">Potvrzení hesla</label>
                    <input class="auth-form__input" type="password" id="reg-password-confirm" name="password_confirm" required minlength="8">
                    <?php if (!empty($errors['password_confirm'])): ?>
                        <ul class="auth-form__errors">
                            <?php foreach ($errors['password_confirm'] as $error): ?>
                                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="auth-form__actions">
                    <button type="submit" class="button">Zaregistrovat se</button>
                    <?php if ($loginUrl): ?>
                        <p class="auth-form__hint">
                            Už máte účet? <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>">Přihlaste se</a>.
                        </p>
                    <?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>
