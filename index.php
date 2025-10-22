<?php
declare(strict_types=1);

require __DIR__ . '/load.php';

cms_bootstrap_config_or_redirect();
cms_bootstrap_cron();

$settings = new Cms\Admin\Settings\CmsSettings();
$links = new Cms\Admin\Utils\LinkGenerator(null, $settings);
$view = new Cms\Front\View\ThemeViewEngine($settings, $links);
$posts = new Cms\Front\Data\PostProvider($links);
$terms = new Cms\Front\Data\TermProvider();
$menus = new Cms\Front\Data\MenuProvider();
$comments = new Cms\Front\Data\CommentProvider($settings);

$router = new Cms\Front\Http\Router($view, $posts, $terms, $menus, $comments, $settings, $links);
$router->dispatch();
