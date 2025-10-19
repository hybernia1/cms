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
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin:0; background:#f7f7f7; color:#222; }
        a { color:#0069d9; text-decoration:none; }
        a:hover { text-decoration:underline; }
        header { background:#ffffff; border-bottom:1px solid #ddd; padding:1.5rem 1rem; }
        header h1 { margin:0; font-size:1.75rem; }
        nav ul { list-style:none; padding:0; margin:0.75rem 0 0; display:flex; flex-wrap:wrap; gap:1rem; }
        nav li { margin:0; }
        main { max-width:960px; margin:0 auto; padding:2rem 1rem; }
        footer { background:#ffffff; border-top:1px solid #ddd; padding:1rem; text-align:center; font-size:0.875rem; }
        .post-list { display:grid; gap:2rem; }
        .post-card { background:#fff; padding:1.5rem; border-radius:12px; box-shadow:0 1px 2px rgba(0,0,0,0.05); }
        .breadcrumbs { font-size:0.875rem; color:#666; margin-bottom:1rem; }
    </style>
</head>
<body>
<header>
    <div class="brand">
        <h1><a href="<?= htmlspecialchars($links->home(), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($site['title'] ?? 'Web', ENT_QUOTES, 'UTF-8'); ?></a></h1>
        <?php if (!empty($site['description'])): ?>
            <p><?= htmlspecialchars((string)$site['description'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </div>
    <?php $primaryNav = $navigation['primary']['items'] ?? []; ?>
    <?php if (!empty($primaryNav)): ?>
        <nav aria-label="Hlavní navigace">
            <ul>
                <?php foreach ($primaryNav as $item): ?>
                    <li>
                        <a href="<?= htmlspecialchars((string)$item['url'], ENT_QUOTES, 'UTF-8'); ?>"<?php if (!empty($item['target']) && $item['target'] !== '_self'): ?> target="<?= htmlspecialchars((string)$item['target'], ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>>
                            <?= htmlspecialchars((string)$item['title'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    <?php endif; ?>
</header>
<main>
    <?php $content(); ?>
</main>
<footer>
    <p>&copy; <?= date('Y'); ?> <?= htmlspecialchars($site['title'] ?? 'Web', ENT_QUOTES, 'UTF-8'); ?>. Pohání jednoduchý CMS.</p>
</footer>
</body>
</html>
