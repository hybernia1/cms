<?php
/** @var array<string,mixed> $old */
/** @var array<string,list<string>> $errors */
/** @var bool $success */
/** @var string|null $message */
/** @var string|null $loginUrl */
/** @var \Cms\Admin\Utils\LinkGenerator $links */

$old = is_array($old) ? $old : [];
$errors = is_array($errors) ? $errors : [];
$success = !empty($success);
$message = isset($message) && $message !== '' ? (string)$message : null;
$loginUrl = isset($loginUrl) && $loginUrl !== '' ? (string)$loginUrl : null;

$oldEmail = htmlspecialchars((string)($old['email'] ?? ''), ENT_QUOTES, 'UTF-8');
$action = htmlspecialchars($links->lost(), ENT_QUOTES, 'UTF-8');
?>
<section>
    <h1>Obnova hesla</h1>

    <?php if ($message !== null): ?>
        <div class="notice"><p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <p>Pokud e-mail existuje v naší databázi, zaslali jsme na něj instrukce k obnovení hesla.</p>
        <?php if ($loginUrl): ?>
            <p><a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>">Zpět na přihlášení</a></p>
        <?php endif; ?>
    <?php else: ?>
        <form method="post" action="<?= $action; ?>">
            <p>
                <label for="lost-email">E-mail</label><br>
                <input type="email" id="lost-email" name="email" value="<?= $oldEmail; ?>" required>
                <?php if (!empty($errors['email'])): ?>
                    <br><small><?= htmlspecialchars(implode(' ', $errors['email']), ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
            </p>
            <p><button type="submit">Odeslat</button></p>
        </form>
    <?php endif; ?>
</section>
