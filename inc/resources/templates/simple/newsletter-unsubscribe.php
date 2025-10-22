<?php
/** @var bool $success */
/** @var string|null $message */
/** @var \Cms\Admin\Utils\LinkGenerator $links */

$success = !empty($success);
$message = isset($message) ? trim((string)$message) : '';
$boxClass = $success ? 'notice' : 'notice-warning';
?>
<section>
    <h1><?= $success ? 'Odběr zrušen' : 'Odhlášení se nezdařilo'; ?></h1>
    <?php if ($message !== ''): ?>
        <div class="<?= htmlspecialchars($boxClass, ENT_QUOTES, 'UTF-8'); ?>">
            <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    <?php endif; ?>
    <p>
        <a href="<?= htmlspecialchars($links->home(), ENT_QUOTES, 'UTF-8'); ?>">Zpět na úvodní stránku</a>
    </p>
</section>
