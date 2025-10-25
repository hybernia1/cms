<?php
declare(strict_types=1);

use Core\Widgets\WidgetRegistry;

function cms_widgets_dir(): string
{
    return defined('BASE_DIR') ? BASE_DIR . '/widgets' : __DIR__ . '/../..' . '/widgets';
}

function cms_bootstrap_widgets(): void
{
    $directory = cms_widgets_dir();
    if (!is_dir($directory)) {
        return;
    }

    $files = [];

    foreach (glob($directory . '/*/widget.php') ?: [] as $file) {
        $files[] = $file;
    }

    foreach (glob($directory . '/*.php') ?: [] as $file) {
        if (basename($file) === 'widget.php') {
            $files[] = $file;
        }
    }

    sort($files);

    foreach ($files as $file) {
        require_once $file;
    }

    cms_do_action('cms_register_widgets', WidgetRegistry::class);
}

function cms_widget_area(string $area, array $context = []): string
{
    return WidgetRegistry::renderArea($area, $context);
}

function cms_render_widget_area(string $area, array $context = []): void
{
    $html = cms_widget_area($area, $context);
    if ($html === '') {
        return;
    }

    echo $html;
}

function cms_has_widgets(string $area): bool
{
    return WidgetRegistry::hasArea($area);
}
