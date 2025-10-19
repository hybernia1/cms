<?php
/** @var array<string,list<string>> $errors */
/** @var string|null $message */
/** @var bool $success */
/** @var bool $allowForm */
/** @var string $token */
/** @var int $userId */
/** @var string|null $loginUrl */
/** @var string|null $lostUrl */
/** @var \Cms\Admin\Utils\LinkGenerator $links */

$errors = is_array($errors) ? $errors : [];
$message = isset($message) && $message !== '' ? (string)$message : null;
$success = !empty($success);
$allowForm = !empty($allowForm);
$token = trim((string)$token);
$userId = (int)$userId;
$loginUrl = isset($loginUrl) && $loginUrl !== '' ? (string)$loginUrl : null;
$lostUrl = isset($lostUrl) && $lostUrl !== '' ? (string)$lostUrl : $links->lost();
$action = htmlspecialchars($links->reset($token, $userId), ENT_QUOTES, 'UTF-8');
?>
<section class="section section--auth section--reset">
    <header class="section__header">
        <p class="section__eyebrow">Reset hesla</p>
        <h1 class="section__title">Nastavte si nové heslo</h1>
        <p class="section__lead">Zadejte nové heslo, které budete používat pro přihlášení. Pro jistotu ho zopakujte ještě jednou.</p>
    </header>

    <?php if ($message !== null): ?>
        <?php
            $noticeType = $success ? 'success' : ($allowForm ? (!empty($errors) ? 'warning' : 'info') : 'danger');
        ?>
        <div class="notice notice--<?= htmlspecialchars($noticeType, ENT_QUOTES, 'UTF-8'); ?>">
            <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    <?php endif; ?>

    <div class="auth-card">
        <?php if ($success): ?>
            <p class="auth-card__text">Heslo bylo úspěšně změněno. Nyní se můžete přihlásit s novými údaji.</p>
            <?php if ($loginUrl): ?>
                <p class="auth-card__actions">
                    <a class="button" href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>">Přejít na přihlášení</a>
                </p>
            <?php endif; ?>
        <?php elseif (!$allowForm): ?>
            <p class="auth-card__text">Odkaz už není platný. Požádejte prosím o <a href="<?= htmlspecialchars($lostUrl, ENT_QUOTES, 'UTF-8'); ?>">vystavení nového</a>.</p>
        <?php else: ?>
            <form method="post" action="<?= $action; ?>" class="auth-form" novalidate>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="user_id" value="<?= htmlspecialchars((string)$userId, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="auth-form__field<?= !empty($errors['password']) ? ' auth-form__field--error' : ''; ?>">
                    <label class="auth-form__label" for="reset-password">Nové heslo</label>
                    <input class="auth-form__input" type="password" id="reset-password" name="password" required minlength="8">
                    <?php if (!empty($errors['password'])): ?>
                        <ul class="auth-form__errors">
                            <?php foreach ($errors['password'] as $error): ?>
                                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="auth-form__field<?= !empty($errors['password_confirm']) ? ' auth-form__field--error' : ''; ?>">
                    <label class="auth-form__label" for="reset-password-confirm">Potvrzení hesla</label>
                    <input class="auth-form__input" type="password" id="reset-password-confirm" name="password_confirm" required minlength="8">
                    <?php if (!empty($errors['password_confirm'])): ?>
                        <ul class="auth-form__errors">
                            <?php foreach ($errors['password_confirm'] as $error): ?>
                                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="auth-form__actions">
                    <button type="submit" class="button">Uložit nové heslo</button>
                    <?php if ($loginUrl): ?>
                        <p class="auth-form__hint">
                            Víte své heslo? <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>">Přejděte na přihlášení</a>.
                        </p>
                    <?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>
