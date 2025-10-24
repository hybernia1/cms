<?php
declare(strict_types=1);

require __DIR__ . '/load.php';

cms_bootstrap_config_or_redirect();

$settings = new Cms\Admin\Settings\CmsSettings();
$links = new Cms\Admin\Utils\LinkGenerator(null, $settings);
$view = new Cms\Front\View\ThemeViewEngine($settings, $links);
$posts = new Cms\Front\Data\PostProvider($links);
$terms = new Cms\Front\Data\TermProvider();
$menus = new Cms\Front\Data\MenuProvider();
$comments = new Cms\Front\Data\CommentProvider($settings);

try {
    $router = new Cms\Front\Http\Router($view, $posts, $terms, $menus, $comments, $settings, $links);
    $router->dispatch();
} catch (Cms\Front\View\MissingThemeException $exception) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');

    echo <<<'HTML'
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title>Chyba šablony</title>
    <style>
        :root { color-scheme: light dark; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f7f7f7;
            color: #1f1f1f;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 1.5rem;
        }
        .error-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 2.5rem;
            box-shadow: 0 10px 35px rgba(15, 23, 42, 0.12);
            max-width: 32rem;
            width: 100%;
            text-align: center;
        }
        .error-card h1 {
            margin: 0 0 1rem;
            font-size: 1.75rem;
        }
        .error-card p {
            margin: 0;
            font-size: 1rem;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <h1>Nebyla nalezena aktivní šablona</h1>
        <p>Zkontrolujte prosím nastavení vzhledu v administrační části.</p>
    </div>
</body>
</html>
HTML;
}
