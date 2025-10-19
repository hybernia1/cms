<?php
/** @var array<string,mixed> $site */
/** @var array<string,mixed> $meta */
/** @var array<string,mixed> $navigation */
/** @var array<string,mixed> $theme */
/** @var \Cms\Admin\Utils\LinkGenerator $links */
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($meta['title'] ?? ($site['title'] ?? 'Web'), ENT_QUOTES, 'UTF-8'); ?></title>
    <?php if (!empty($meta['description'])): ?>
        <meta name="description" content="<?= htmlspecialchars((string)$meta['description'], ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php if (!empty($meta['canonical'])): ?>
        <link rel="canonical" href="<?= htmlspecialchars((string)$meta['canonical'], ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php $asset = is_callable($theme['asset'] ?? null) ? $theme['asset'] : static fn(string $path): string => $path; ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($asset('assets/style.css'), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
<div class="site-wrapper">
    <header class="site-header">
        <div class="site-brand">
            <p class="site-title">
                <a href="<?= htmlspecialchars($links->home(), ENT_QUOTES, 'UTF-8'); ?>">
                    <?= htmlspecialchars($site['title'] ?? 'Web', ENT_QUOTES, 'UTF-8'); ?>
                </a>
            </p>
            <?php if (!empty($site['description'])): ?>
                <p class="site-tagline"><?= htmlspecialchars((string)$site['description'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <?php $primaryNav = $navigation['primary']['items'] ?? []; ?>
            <?php if (!empty($primaryNav)): ?>
                <nav class="site-nav" aria-label="Hlavní navigace">
                    <ul>
                        <?php foreach ($primaryNav as $item): ?>
                            <li>
                                <a href="<?= htmlspecialchars((string)$item['url'], ENT_QUOTES, 'UTF-8'); ?>"<?php if (!empty($item['target']) && $item['target'] !== '_self'): ?> target="<?= htmlspecialchars((string)$item['target'], ENT_QUOTES, 'UTF-8'); ?>" rel="noopener"<?php endif; ?>>
                                    <?= htmlspecialchars((string)$item['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </header>
    <main class="site-main">
        <?php $content(); ?>
    </main>
    <footer class="site-footer">
        <div class="footer-inner">
            <p>&copy; <?= date('Y'); ?> <?= htmlspecialchars($site['title'] ?? 'Web', ENT_QUOTES, 'UTF-8'); ?>. Vytvořeno s důrazem na klasický vzhled.</p>
            <?php $footerNav = $navigation['footer']['items'] ?? []; ?>
            <?php if (!empty($footerNav)): ?>
                <nav class="footer-nav" aria-label="Patičkové odkazy">
                    <ul>
                        <?php foreach ($footerNav as $item): ?>
                            <li>
                                <a href="<?= htmlspecialchars((string)$item['url'], ENT_QUOTES, 'UTF-8'); ?>"<?php if (!empty($item['target']) && $item['target'] !== '_self'): ?> target="<?= htmlspecialchars((string)$item['target'], ENT_QUOTES, 'UTF-8'); ?>" rel="noopener"<?php endif; ?>>
                                    <?= htmlspecialchars((string)$item['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </footer>
</div>
</body>
</html>
