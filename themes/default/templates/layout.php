<?php
/** @var array<string,mixed> $site */
/** @var array<string,mixed> $meta */
/** @var array<string,mixed> $navigation */
/** @var array<string,mixed> $theme */
/** @var \Cms\Admin\Utils\LinkGenerator $links */
/** @var list<array{type:string,message:string}> $notifications */
/** @var array<string,mixed>|null $currentUser */
/** @var array<string,mixed> $cart */

$asset = is_callable($theme['asset'] ?? null) ? $theme['asset'] : static fn (string $path): string => $path;
$locale = (string)($site['locale'] ?? 'cs');
$locale = $locale !== '' ? $locale : 'cs';
$siteTitle = (string)($site['title'] ?? 'Storefront');
$siteTagline = (string)($site['description'] ?? '');
$siteFavicon = (string)($site['favicon'] ?? '');
$metaTitle = (string)($meta['title'] ?? $siteTitle);
$metaDescription = isset($meta['description']) && $meta['description'] !== '' ? (string)$meta['description'] : null;
$canonical = isset($meta['canonical']) && $meta['canonical'] !== '' ? (string)$meta['canonical'] : null;
$metaExtra = is_array($meta['extra'] ?? null) ? $meta['extra'] : [];
$structuredData = is_array($meta['structured_data'] ?? null) ? $meta['structured_data'] : [];
$themeName = (string)($theme['name'] ?? 'Default Storefront');
$themeVersion = trim((string)($theme['version'] ?? ''));
$palette = is_array($theme['palette'] ?? null) ? $theme['palette'] : [];
$themeColor = isset($palette['accent']) ? (string)$palette['accent'] : '';
$paletteMap = [
    'background' => '--store-bg',
    'accent' => '--store-accent',
    'accent_light' => '--store-accent-light',
    'text' => '--store-text',
    'muted' => '--store-muted',
    'border' => '--store-border',
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
$themeSlug = preg_replace('~[^a-z0-9\-]~i', '', (string)($theme['slug'] ?? 'default'));
$bodyClass = 'theme-' . ($themeSlug !== '' ? strtolower($themeSlug) : 'default');
$bodyClasses = [$bodyClass, 'storefront'];
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

$homeUrl = $links->home();
$catalogUrl = $links->products();
$checkoutUrl = $links->checkout();

$cartCount = isset($cart['count']) ? (int)$cart['count'] : 0;
$cartTotal = isset($cart['total']) ? (float)$cart['total'] : 0.0;
$cartCurrency = isset($cart['currency']) ? (string)$cart['currency'] : '';
$cartSummary = '';
if ($cartTotal > 0.0) {
    $cartSummary = number_format($cartTotal, 2, ',', '\u{00A0}') . ($cartCurrency !== '' ? ' ' . $cartCurrency : '');
}

$notificationsList = [];
if (is_array($notifications ?? null)) {
    $notificationsList = $notifications;
}

$currentUser = is_array($currentUser ?? null) ? $currentUser : null;
$primaryNav = is_array($navigation['primary']['items'] ?? null) ? $navigation['primary']['items'] : [];
$footerNav = is_array($navigation['footer']['items'] ?? null) ? $navigation['footer']['items'] : [];

$collectMenuItems = static function (array $items) use (&$collectMenuItems): array {
    $collected = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $title = trim((string)($item['title'] ?? ''));
        $url = trim((string)($item['url'] ?? ''));
        if ($title === '' || $url === '') {
            continue;
        }
        $collected[] = [
            'title' => $title,
            'url' => $url,
            'target' => (string)($item['target'] ?? '_self'),
        ];
        if (isset($item['children']) && is_array($item['children'])) {
            $collected = array_merge($collected, $collectMenuItems($item['children']));
        }
    }

    return $collected;
};

$extraNavItems = $collectMenuItems($primaryNav);
$skipUrls = [
    rtrim($homeUrl, '/'),
    rtrim($catalogUrl, '/'),
    rtrim($checkoutUrl, '/'),
];
$extraNavItems = array_values(array_filter($extraNavItems, static function (array $item) use ($skipUrls): bool {
    $normalized = rtrim((string)$item['url'], '/');
    return $normalized === '' || !in_array($normalized, $skipUrls, true);
}));

$renderSimpleMenu = static function (array $items, string $listClass, string $itemClass, string $linkClass): string {
    if ($items === []) {
        return '';
    }

    $htmlItems = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $title = trim((string)($item['title'] ?? ($item['name'] ?? '')));
        $url = trim((string)($item['url'] ?? ''));
        if ($title === '' || $url === '') {
            continue;
        }
        $target = (string)($item['target'] ?? '_self');
        $relAttr = '';
        if ($target !== '' && $target !== '_self') {
            $relAttr = ' rel="noopener noreferrer"';
        }
        $htmlItems[] = sprintf(
            '<li class="%s"><a class="%s" href="%s"%s%s>%s</a></li>',
            htmlspecialchars($itemClass, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($linkClass, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
            $target !== '' && $target !== '_self' ? ' target="' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '"' : '',
            $relAttr,
            htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
        );
    }

    if ($htmlItems === []) {
        return '';
    }

    return sprintf(
        '<ul class="%s">%s</ul>',
        htmlspecialchars($listClass, ENT_QUOTES, 'UTF-8'),
        implode('', $htmlItems)
    );
};

$footerMenuHtml = $renderSimpleMenu($collectMenuItems($footerNav), 'store-footer__menu', 'store-footer__menu-item', 'store-footer__menu-link');
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
    <?php if ($siteFavicon !== ''): ?>
        <link rel="icon" href="<?= htmlspecialchars($siteFavicon, ENT_QUOTES, 'UTF-8'); ?>">
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
    <?php foreach ($structuredData as $schema): ?>
        <?php
            if (!is_array($schema)) {
                continue;
            }
            $jsonOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
            $schemaJson = json_encode($schema, $jsonOptions);
            if (!is_string($schemaJson)) {
                continue;
            }
        ?>
        <script type="application/ld+json">
<?= $schemaJson . "\n"; ?>
        </script>
    <?php endforeach; ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($asset('assets/style.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($paletteCss !== []): ?>
        <style>:root { <?= htmlspecialchars(implode(';', $paletteCss), ENT_QUOTES, 'UTF-8'); ?>; }</style>
    <?php endif; ?>
    <?php if (function_exists('cms_do_action')) { cms_do_action('cms_front_head'); } ?>
</head>
<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8'); ?>">
<a class="skip-link" href="#main">Přejít k obsahu</a>
<header class="store-header">
    <div class="store-header__inner">
        <div class="store-header__brand-block">
            <a class="store-header__brand" href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8'); ?>">
                <?= htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <?php if ($siteTagline !== ''): ?>
                <p class="store-header__tagline"><?= htmlspecialchars($siteTagline, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <?php if ($currentUser !== null): ?>
                <div class="store-header__user">
                    <span class="store-header__user-name"><?= htmlspecialchars((string)($currentUser['name'] ?? 'Správce'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php if (!empty($currentUser['admin_url'])): ?>
                        <a class="store-header__user-link" href="<?= htmlspecialchars((string)$currentUser['admin_url'], ENT_QUOTES, 'UTF-8'); ?>">Administrace</a>
                    <?php endif; ?>
                    <?php if (!empty($currentUser['logout_url'])): ?>
                        <a class="store-header__user-link" href="<?= htmlspecialchars((string)$currentUser['logout_url'], ENT_QUOTES, 'UTF-8'); ?>">Odhlásit se</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <nav class="store-header__nav" aria-label="Hlavní navigace">
            <ul class="store-menu">
                <li class="store-menu__item">
                    <a class="store-menu__link" href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8'); ?>">Domů</a>
                </li>
                <li class="store-menu__item">
                    <a class="store-menu__link" href="<?= htmlspecialchars($catalogUrl, ENT_QUOTES, 'UTF-8'); ?>">Katalog</a>
                </li>
                <li class="store-menu__item">
                    <a class="store-menu__link" href="<?= htmlspecialchars($checkoutUrl, ENT_QUOTES, 'UTF-8'); ?>">Pokladna</a>
                </li>
                <?php foreach ($extraNavItems as $item): ?>
                    <li class="store-menu__item">
                        <?php
                            $target = (string)($item['target'] ?? '_self');
                            $relAttr = '';
                            if ($target !== '' && $target !== '_self') {
                                $relAttr = ' rel="noopener noreferrer"';
                            }
                        ?>
                        <a class="store-menu__link" href="<?= htmlspecialchars((string)$item['url'], ENT_QUOTES, 'UTF-8'); ?>"<?php if ($target !== '' && $target !== '_self'): ?> target="<?= htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?>"<?= $relAttr; ?><?php endif; ?>>
                            <?= htmlspecialchars((string)$item['title'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <a class="store-header__cart" href="<?= htmlspecialchars($checkoutUrl, ENT_QUOTES, 'UTF-8'); ?>">
            <span class="store-header__cart-label">Košík</span>
            <span class="store-header__cart-count" aria-label="Počet položek v košíku"><?= $cartCount; ?></span>
            <?php if ($cartSummary !== ''): ?>
                <span class="store-header__cart-total"><?= htmlspecialchars($cartSummary, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
        </a>
    </div>
</header>
<?php if ($notificationsList !== []): ?>
    <div class="store-notifications" role="status" aria-live="polite">
        <?php foreach ($notificationsList as $notification): ?>
            <?php
                $type = (string)($notification['type'] ?? 'info');
                $type = in_array($type, ['info', 'success', 'warning', 'danger'], true) ? $type : 'info';
                $message = (string)($notification['message'] ?? '');
                if ($message === '') {
                    continue;
                }
            ?>
            <div class="notice notice--<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>">
                <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<main class="store-main" id="main">
    <?php $content(); ?>
</main>
<footer class="store-footer">
    <div class="store-footer__inner">
        <div class="store-footer__brand">
            <p>&copy; <?= date('Y'); ?> <?= htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8'); ?>. Vše pro váš katalog produktů.</p>
        </div>
        <?php if ($footerMenuHtml !== ''): ?>
            <nav class="store-footer__nav" aria-label="Doplňkové odkazy">
                <?= $footerMenuHtml; ?>
            </nav>
        <?php endif; ?>
    </div>
</footer>
<?php if (function_exists('cms_do_action')) { cms_do_action('cms_front_footer'); } ?>
</body>
</html>
