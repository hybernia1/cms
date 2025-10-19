<?php
/** @var array<string,mixed> $site */
/** @var array<string,mixed> $meta */
/** @var array<string,mixed> $navigation */
/** @var array<string,mixed> $theme */
/** @var \Cms\Admin\Utils\LinkGenerator $links */

$asset = is_callable($theme['asset'] ?? null) ? $theme['asset'] : static fn (string $path): string => $path;
$locale = (string)($site['locale'] ?? 'cs');
$locale = $locale !== '' ? $locale : 'cs';
$siteTitle = (string)($site['title'] ?? 'Web');
$siteTagline = (string)($site['description'] ?? '');
$metaTitle = (string)($meta['title'] ?? $siteTitle);
$metaDescription = isset($meta['description']) && $meta['description'] !== '' ? (string)$meta['description'] : null;
$canonical = isset($meta['canonical']) && $meta['canonical'] !== '' ? (string)$meta['canonical'] : null;
$metaExtra = is_array($meta['extra'] ?? null) ? $meta['extra'] : [];
$themeName = (string)($theme['name'] ?? 'Classic');
$themeVersion = trim((string)($theme['version'] ?? ''));
$palette = is_array($theme['palette'] ?? null) ? $theme['palette'] : [];
$themeColor = isset($palette['accent']) ? (string)$palette['accent'] : '';
$paletteMap = [
    'background' => '--classic-bg',
    'accent' => '--classic-accent',
    'accent_light' => '--classic-accent-light',
    'text' => '--classic-text',
    'muted' => '--classic-muted',
    'border' => '--classic-border',
    'card' => '--classic-card',
];
$paletteCss = [];
foreach ($paletteMap as $paletteKey => $cssVar) {
    if (!isset($palette[$paletteKey])) {
        continue;
    }
    $value = trim((string)$palette[$paletteKey]);
    if ($value === '') {
        continue;
    }
    $paletteCss[] = sprintf('%s:%s', $cssVar, $value);
}
$themeSlug = preg_replace('~[^a-z0-9\-]~i', '', (string)($theme['slug'] ?? 'classic'));
$bodyClass = 'theme-' . ($themeSlug !== '' ? strtolower($themeSlug) : 'classic');
$bodyClasses = [$bodyClass];
$metaBody = isset($meta['body_class']) ? (string)$meta['body_class'] : '';
if ($metaBody !== '') {
    foreach (preg_split('~\s+~', $metaBody) as $class) {
        $class = trim($class);
        if ($class === '') {
            continue;
        }
        $bodyClasses[] = preg_replace('~[^a-z0-9\-]~i', '', strtolower($class));
    }
}
$bodyClass = trim(implode(' ', array_unique(array_filter($bodyClasses))));
$primaryNav = $navigation['primary']['items'] ?? [];
$footerNav = $navigation['footer']['items'] ?? [];

$renderMenu = static function (array $items, string $class = 'menu', int $depth = 0) use (&$renderMenu): string {
    if ($items === []) {
        return '';
    }

    ob_start();
    ?>
    <ul class="<?= htmlspecialchars($class . ($depth > 0 ? ' menu--sub' : ''), ENT_QUOTES, 'UTF-8'); ?>">
        <?php foreach ($items as $item): ?>
            <?php
                $children = is_array($item['children'] ?? null) ? $item['children'] : [];
                $itemClasses = ['menu__item'];
                if ($children !== []) {
                    $itemClasses[] = 'menu__item--has-children';
                }
                $customClass = trim((string)($item['css_class'] ?? ''));
                if ($customClass !== '') {
                    $sanitized = preg_replace('~[^a-z0-9\-\s_]~i', '', $customClass);
                    if ($sanitized !== '') {
                        $itemClasses[] = $sanitized;
                    }
                }
                $target = (string)($item['target'] ?? '_self');
                $href = (string)($item['url'] ?? '');
                $relAttr = '';
                if ($target !== '' && $target !== '_self') {
                    $relAttr = ' rel="noopener noreferrer"';
                }
            ?>
            <li class="<?= htmlspecialchars(trim(implode(' ', array_filter($itemClasses))), ENT_QUOTES, 'UTF-8'); ?>">
                <a class="menu__link" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"<?php if ($target !== '' && $target !== '_self'): ?> target="<?= htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?>"<?= $relAttr; ?><?php endif; ?>>
                    <?= htmlspecialchars((string)($item['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                </a>
                <?php if ($children !== []): ?>
                    <?= $renderMenu($children, 'menu menu--sub', $depth + 1); ?>
                <?php endif; ?>
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
    <title><?= htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php if ($metaDescription !== null): ?>
        <meta name="description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php if ($canonical !== null): ?>
        <link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php if ($themeColor !== ''): ?>
        <meta name="theme-color" content="<?= htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php if ($themeName !== ''): ?>
        <meta name="generator" content="<?= htmlspecialchars($themeName . ($themeVersion !== '' ? ' v' . $themeVersion : ''), ENT_QUOTES, 'UTF-8'); ?>">
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
                if (str_starts_with((string)$key, 'twitter:')) {
                    $attr = 'name';
                }
                $attrValue = (string)$key;
            }
            if ($metaContent === '') {
                continue;
            }
        ?>
        <meta <?= $attr; ?>="<?= htmlspecialchars($attrValue, ENT_QUOTES, 'UTF-8'); ?>" content="<?= htmlspecialchars($metaContent, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($asset('assets/style.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($paletteCss !== []): ?>
        <style>:root { <?= htmlspecialchars(implode(';', $paletteCss), ENT_QUOTES, 'UTF-8'); ?>; }</style>
    <?php endif; ?>
</head>
<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8'); ?>">
<a class="skip-link" href="#main">Přejít k obsahu</a>
<div class="site-wrapper">
    <header class="site-header">
        <div class="site-brand">
            <p class="site-title">
                <a href="<?= htmlspecialchars($links->home(), ENT_QUOTES, 'UTF-8'); ?>">
                    <?= htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8'); ?>
                </a>
            </p>
            <?php if ($siteTagline !== ''): ?>
                <p class="site-tagline"><?= htmlspecialchars($siteTagline, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <?php $primaryMenu = $renderMenu(is_array($primaryNav) ? $primaryNav : [], 'menu menu--primary'); ?>
            <?php if ($primaryMenu !== ''): ?>
                <nav class="site-nav" aria-label="Hlavní navigace">
                    <?= $primaryMenu; ?>
                </nav>
            <?php endif; ?>
        </div>
    </header>
    <main class="site-main" id="main">
        <?php $content(); ?>
    </main>
    <footer class="site-footer">
        <div class="footer-inner">
            <p>&copy; <?= date('Y'); ?> <?= htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8'); ?>. Vytvořeno s důrazem na klasický vzhled.</p>
            <?php $footerMenu = $renderMenu(is_array($footerNav) ? $footerNav : [], 'menu menu--footer'); ?>
            <?php if ($footerMenu !== ''): ?>
                <nav class="footer-nav" aria-label="Patičkové odkazy">
                    <?= $footerMenu; ?>
                </nav>
            <?php endif; ?>
        </div>
    </footer>
</div>
</body>
</html>
