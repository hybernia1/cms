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
$loginUrl = isset($loginUrl) && $loginUrl !== '' ? (string)$loginUrl : null;

$oldName = htmlspecialchars((string)($old['name'] ?? ''), ENT_QUOTES, 'UTF-8');
$oldEmail = htmlspecialchars((string)($old['email'] ?? ''), ENT_QUOTES, 'UTF-8');
$action = htmlspecialchars($links->register(), ENT_QUOTES, 'UTF-8');
?>
<section>
    <h1>Registrace</h1>

    <?php if ($message !== null): ?>
        <div class="notice"><p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <p>Registrace proběhla v pořádku.</p>
        <?php if ($loginUrl): ?>
            <p><a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>">Přejít na přihlášení</a></p>
        <?php endif; ?>
    <?php elseif (!$allowed): ?>
        <p>Registrace je momentálně vypnutá.</p>
    <?php else: ?>
        <form method="post" action="<?= $action; ?>">
            <p>
                <label for="reg-name">Jméno</label><br>
                <input type="text" id="reg-name" name="name" value="<?= $oldName; ?>" required>
                <?php if (!empty($errors['name'])): ?>
                    <br><small><?= htmlspecialchars(implode(' ', $errors['name']), ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
            </p>

            <p>
                <label for="reg-email">E-mail</label><br>
                <input type="email" id="reg-email" name="email" value="<?= $oldEmail; ?>" required>
                <?php if (!empty($errors['email'])): ?>
                    <br><small><?= htmlspecialchars(implode(' ', $errors['email']), ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
            </p>

            <p>
                <label for="reg-password">Heslo</label><br>
                <input type="password" id="reg-password" name="password" required minlength="8">
                <?php if (!empty($errors['password'])): ?>
                    <br><small><?= htmlspecialchars(implode(' ', $errors['password']), ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
            </p>

            <p>
                <label for="reg-password-confirm">Potvrzení hesla</label><br>
                <input type="password" id="reg-password-confirm" name="password_confirm" required minlength="8">
                <?php if (!empty($errors['password_confirm'])): ?>
                    <br><small><?= htmlspecialchars(implode(' ', $errors['password_confirm']), ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
            </p>

            <p><button type="submit">Zaregistrovat se</button></p>
        </form>
    <?php endif; ?>
</section>
