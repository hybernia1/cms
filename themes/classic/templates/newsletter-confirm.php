<?php
/** @var bool $success */
/** @var string|null $message */
/** @var \Cms\Admin\Utils\LinkGenerator $links */

$success = !empty($success);
$message = isset($message) ? trim((string)$message) : '';
?>
<section class="section section--newsletter-result">
    <header class="section__header">
        <p class="section__eyebrow">Newsletter</p>
        <h1 class="section__title">
            <?= $success ? 'Odběr potvrzen' : 'Potvrzení selhalo'; ?>
        </h1>
    </header>

    <div class="section__content">
        <div class="notice notice--<?= $success ? 'success' : 'warning'; ?>">
            <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <p>
            <a class="button" href="<?= htmlspecialchars($links->home(), ENT_QUOTES, 'UTF-8'); ?>">Zpět na úvodní stránku</a>
        </p>
    </div>
</section>
