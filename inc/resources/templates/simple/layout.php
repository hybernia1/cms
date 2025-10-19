<?php
/** @var array<string,mixed> $site */
/** @var array<string,mixed> $meta */
/** @var array<string,mixed> $navigation */
/** @var array<string,mixed> $theme */
/** @var \Cms\Admin\Utils\LinkGenerator $links */

$locale = (string)($site['locale'] ?? 'cs');
$locale = $locale !== '' ? $locale : 'cs';
$title = (string)($meta['title'] ?? ($site['title'] ?? 'Web'));
$description = isset($meta['description']) ? (string)$meta['description'] : '';
$canonical = isset($meta['canonical']) ? (string)$meta['canonical'] : '';
$metaExtra = is_array($meta['extra'] ?? null) ? $meta['extra'] : [];
$primaryNav = $navigation['primary']['items'] ?? [];
$bodyClasses = ['theme-simple'];
if (!empty($meta['body_class'])) {
    $bodyClasses[] = preg_replace('~[^a-z0-9\-]~i', '', strtolower((string)$meta['body_class']));
}
$bodyClass = trim(implode(' ', array_filter(array_unique($bodyClasses))));

$renderMenu = static function (array $items): string {
    if ($items === []) {
        return '';
    }
    ob_start();
    ?>
    <ul class="menu">
        <?php foreach ($items as $item): ?>
            <li>
                <a href="<?= htmlspecialchars((string)($item['url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"<?php if (!empty($item['target']) && $item['target'] !== '_self'): ?> target="<?= htmlspecialchars((string)$item['target'], ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>>
                    <?= htmlspecialchars((string)($item['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
    return trim((string)ob_get_clean());
};
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php if ($description !== ''): ?>
        <meta name="description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php if ($canonical !== ''): ?>
        <link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php foreach ($metaExtra as $key => $value): ?>
        <?php
            if (is_array($value)) {
                $metaContent = isset($value['content']) ? (string)$value['content'] : '';
                $attr = isset($value['property']) ? 'property' : (isset($value['name']) ? 'name' : (str_starts_with((string)$key, 'og:') ? 'property' : 'name'));
                $attrValue = (string)($value['property'] ?? ($value['name'] ?? $key));
            } else {
                $metaContent = (string)$value;
                $attr = str_starts_with((string)$key, 'og:') ? 'property' : 'name';
                $attrValue = (string)$key;
            }
            if ($metaContent === '') {
                continue;
            }
        ?>
        <meta <?= $attr; ?>="<?= htmlspecialchars($attrValue, ENT_QUOTES, 'UTF-8'); ?>" content="<?= htmlspecialchars($metaContent, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach; ?>
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin:0; background:#f3f4f6; color:#1f2933; line-height:1.6; }
        a { color:#0b7285; text-decoration:none; }
        a:hover, a:focus { text-decoration:underline; }
        .skip-link { position:absolute; left:-9999px; top:auto; width:1px; height:1px; overflow:hidden; }
        .skip-link:focus { position:static; width:auto; height:auto; padding:0.5rem 0.75rem; background:#0b7285; color:#fff; border-radius:999px; margin:0.5rem; display:inline-block; }
        header { background:#fff; border-bottom:1px solid #d0d7de; padding:1.75rem 1rem; }
        .brand { max-width:960px; margin:0 auto; }
        .brand h1 { margin:0; font-size:2rem; }
        nav { max-width:960px; margin:0.75rem auto 0; }
        .menu { list-style:none; margin:0; padding:0; display:flex; flex-wrap:wrap; gap:1rem; }
        main { max-width:960px; margin:0 auto; padding:2.5rem 1rem; }
        footer { background:#fff; border-top:1px solid #d0d7de; padding:1.5rem 1rem; font-size:0.9rem; text-align:center; color:#52606d; }
        .post-list { display:grid; gap:1.5rem; }
        .post-card { background:#fff; padding:1.5rem; border-radius:12px; box-shadow:0 1px 2px rgba(15,23,42,0.08); }
        .post-card__meta { margin:0 0 0.5rem; font-size:0.85rem; color:#52606d; }
        .notice { border-radius:12px; padding:1rem 1.25rem; background:#edf2f7; border:1px solid #d0d7de; }
    </style>
</head>
<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8'); ?>">
<a class="skip-link" href="#main">Přejít k obsahu</a>
<header>
    <div class="brand">
        <h1><a href="<?= htmlspecialchars($links->home(), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($site['title'] ?? 'Web', ENT_QUOTES, 'UTF-8'); ?></a></h1>
        <?php if (!empty($site['description'])): ?>
            <p><?= htmlspecialchars((string)$site['description'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </div>
    <?php $primaryMenu = $renderMenu(is_array($primaryNav) ? $primaryNav : []); ?>
    <?php if ($primaryMenu !== ''): ?>
        <nav aria-label="Hlavní navigace">
            <?= $primaryMenu; ?>
        </nav>
    <?php endif; ?>
</header>
<main id="main">
    <?php $content(); ?>
</main>
<footer>
    <p>&copy; <?= date('Y'); ?> <?= htmlspecialchars($site['title'] ?? 'Web', ENT_QUOTES, 'UTF-8'); ?>. Pohání jednoduchý CMS.</p>
</footer>
</body>
</html>
