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
$token = (string)$token;
$userId = (int)$userId;
$loginUrl = isset($loginUrl) && $loginUrl !== '' ? (string)$loginUrl : null;
$lostUrl = isset($lostUrl) && $lostUrl !== '' ? (string)$lostUrl : $links->lost();
$action = htmlspecialchars($links->reset($token, $userId), ENT_QUOTES, 'UTF-8');
?>
<section>
    <h1>Reset hesla</h1>

    <?php if ($message !== null): ?>
        <div class="notice"><p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <p>Heslo bylo změněno.</p>
        <?php if ($loginUrl): ?>
            <p><a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>">Přejít na přihlášení</a></p>
        <?php endif; ?>
    <?php elseif (!$allowForm): ?>
        <p>Odkaz už není platný. <a href="<?= htmlspecialchars($lostUrl, ENT_QUOTES, 'UTF-8'); ?>">Vyžádejte si nový.</a></p>
    <?php else: ?>
        <form method="post" action="<?= $action; ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="user_id" value="<?= htmlspecialchars((string)$userId, ENT_QUOTES, 'UTF-8'); ?>">
            <p>
                <label for="reset-password">Nové heslo</label><br>
                <input type="password" id="reset-password" name="password" required minlength="8">
                <?php if (!empty($errors['password'])): ?>
                    <br><small><?= htmlspecialchars(implode(' ', $errors['password']), ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
            </p>
            <p>
                <label for="reset-password-confirm">Potvrzení hesla</label><br>
                <input type="password" id="reset-password-confirm" name="password_confirm" required minlength="8">
                <?php if (!empty($errors['password_confirm'])): ?>
                    <br><small><?= htmlspecialchars(implode(' ', $errors['password_confirm']), ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
            </p>
            <p><button type="submit">Uložit</button></p>
        </form>
    <?php endif; ?>
</section>
