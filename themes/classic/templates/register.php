<?php
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
<article class="post-content register">
    <h1>Registrace</h1>

    <?php if ($message !== null): ?>
        <div class="register__alert<?= $success ? ' register__alert--success' : ($allowed ? ' register__alert--info' : ' register__alert--danger'); ?>">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if ($success && $loginUrl): ?>
        <p class="register__next">
            <a class="register__button" href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>">Přejít na přihlášení</a>
        </p>
    <?php endif; ?>

    <?php if (!$success && $allowed): ?>
        <form method="post" action="<?= $action; ?>" class="register__form">
            <div class="register__field">
                <label for="reg-name">Jméno</label>
                <input type="text" id="reg-name" name="name" value="<?= $oldName; ?>" required>
                <?php if (!empty($errors['name'])): ?>
                    <ul class="register__errors">
                        <?php foreach ($errors['name'] as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="register__field">
                <label for="reg-email">E-mail</label>
                <input type="email" id="reg-email" name="email" value="<?= $oldEmail; ?>" required>
                <?php if (!empty($errors['email'])): ?>
                    <ul class="register__errors">
                        <?php foreach ($errors['email'] as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="register__field">
                <label for="reg-password">Heslo</label>
                <input type="password" id="reg-password" name="password" required minlength="8">
                <?php if (!empty($errors['password'])): ?>
                    <ul class="register__errors">
                        <?php foreach ($errors['password'] as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="register__field">
                <label for="reg-password-confirm">Potvrzení hesla</label>
                <input type="password" id="reg-password-confirm" name="password_confirm" required minlength="8">
                <?php if (!empty($errors['password_confirm'])): ?>
                    <ul class="register__errors">
                        <?php foreach ($errors['password_confirm'] as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="register__actions">
                <button type="submit" class="register__button">Zaregistrovat se</button>
            </div>
        </form>
    <?php endif; ?>
</article>

<style>
    .register {
        display: grid;
        gap: 1.5rem;
    }
    .register label {
        font-weight: 600;
    }
    .register input[type="text"],
    .register input[type="email"],
    .register input[type="password"] {
        width: 100%;
        padding: 0.75rem 1rem;
        border-radius: 12px;
        border: 1px solid rgba(42, 77, 105, 0.2);
        font-size: 1rem;
        background: rgba(255, 255, 255, 0.9);
    }
    .register__form {
        display: grid;
        gap: 1.5rem;
    }
    .register__errors {
        margin: 0.5rem 0 0;
        padding-left: 1.25rem;
        color: #7f1d1d;
    }
    .register__errors li {
        margin: 0.25rem 0;
    }
    .register__alert {
        padding: 1rem 1.25rem;
        border-radius: 12px;
        border: 1px solid rgba(42, 77, 105, 0.2);
        background: rgba(255, 255, 255, 0.85);
    }
    .register__alert--success {
        border-color: #6fbf73;
        background: #f0fff4;
    }
    .register__alert--danger {
        border-color: #d87a6d;
        background: #fff5f2;
    }
    .register__alert--info {
        border-color: rgba(42, 77, 105, 0.3);
        background: rgba(42, 77, 105, 0.08);
    }
    .register__button {
        display: inline-block;
        background: var(--classic-accent);
        color: #fff;
        padding: 0.75rem 1.75rem;
        border-radius: 999px;
        border: none;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        cursor: pointer;
        text-decoration: none;
    }
    .register__button:hover,
    .register__button:focus {
        background: var(--classic-accent-light);
    }
    .register__actions {
        margin-top: 0.5rem;
    }
</style>
