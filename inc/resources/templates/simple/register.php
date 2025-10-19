<?php
/** @var array<string,mixed> $meta */
/** @var array<string,mixed> $old */
/** @var array<string,list<string>> $errors */
/** @var bool $success */
/** @var bool $allowed */
/** @var string|null $message */
/** @var string|null $loginUrl */
/** @var \Cms\Admin\Utils\LinkGenerator $links */

$old = is_array($old) ? $old : [];
$errors = is_array($errors) ? $errors : [];
$success = !empty($success);
$allowed = isset($allowed) ? (bool)$allowed : true;
$message = isset($message) && $message !== '' ? (string)$message : null;
$loginUrl = isset($loginUrl) ? (string)$loginUrl : null;

$oldName = htmlspecialchars((string)($old['name'] ?? ''), ENT_QUOTES, 'UTF-8');
$oldEmail = htmlspecialchars((string)($old['email'] ?? ''), ENT_QUOTES, 'UTF-8');

$action = htmlspecialchars($links->register(), ENT_QUOTES, 'UTF-8');
?>
<section class="register">
    <h1>Registrace</h1>

    <?php if ($message !== null): ?>
        <div class="notice<?= $success ? ' notice--success' : ($allowed ? ' notice--warning' : ' notice--error'); ?>">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if ($success && $loginUrl): ?>
        <p>
            <a class="button" href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>">Přejít na přihlášení</a>
        </p>
    <?php endif; ?>

    <?php if (!$success && $allowed): ?>
        <form method="post" action="<?= $action; ?>" class="form">
            <div class="form-field">
                <label for="reg-name">Jméno</label>
                <input type="text" id="reg-name" name="name" value="<?= $oldName; ?>" required>
                <?php if (!empty($errors['name'])): ?>
                    <ul class="errors">
                        <?php foreach ($errors['name'] as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="form-field">
                <label for="reg-email">E-mail</label>
                <input type="email" id="reg-email" name="email" value="<?= $oldEmail; ?>" required>
                <?php if (!empty($errors['email'])): ?>
                    <ul class="errors">
                        <?php foreach ($errors['email'] as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="form-field">
                <label for="reg-password">Heslo</label>
                <input type="password" id="reg-password" name="password" required minlength="8">
                <?php if (!empty($errors['password'])): ?>
                    <ul class="errors">
                        <?php foreach ($errors['password'] as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="form-field">
                <label for="reg-password-confirm">Potvrzení hesla</label>
                <input type="password" id="reg-password-confirm" name="password_confirm" required minlength="8">
                <?php if (!empty($errors['password_confirm'])): ?>
                    <ul class="errors">
                        <?php foreach ($errors['password_confirm'] as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button type="submit">Zaregistrovat se</button>
            </div>
        </form>
    <?php endif; ?>
</section>

<style>
    .register .notice {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        background: #f1f5f9;
        border: 1px solid #cbd5f5;
    }
    .register .notice--success {
        background: #e6ffed;
        border-color: #8fd19e;
    }
    .register .notice--warning,
    .register .notice--error {
        background: #fff4e5;
        border-color: #f0a96c;
    }
    .register .form {
        display: grid;
        gap: 1.5rem;
        max-width: 520px;
    }
    .register label {
        font-weight: 600;
        display: block;
        margin-bottom: 0.5rem;
    }
    .register input[type="text"],
    .register input[type="email"],
    .register input[type="password"] {
        width: 100%;
        padding: 0.75rem;
        border-radius: 8px;
        border: 1px solid #cbd5f5;
        font-size: 1rem;
    }
    .register .errors {
        margin: 0.5rem 0 0;
        padding-left: 1.25rem;
        color: #b91c1c;
    }
    .register .errors li {
        margin: 0.25rem 0;
    }
    .register .form-actions {
        display: flex;
        justify-content: flex-start;
    }
    .register button,
    .register .button {
        background: #0d6efd;
        color: #fff;
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 999px;
        font-size: 1rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
    }
    .register button:hover,
    .register .button:hover {
        background: #0a58ca;
    }
</style>
