<?php
declare(strict_types=1);

use Core\Widgets\WidgetRegistry;
use Core\Widgets\WidgetSettingsStore;

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

/**
 * @return array{active:bool,options:array<string,mixed>}
 */
function cms_widget_settings(string $id): array
{
    return WidgetSettingsStore::get($id);
}

function cms_widget_option(string $id, string $key, mixed $default = null): mixed
{
    $settings = WidgetSettingsStore::get($id);
    if (array_key_exists($key, $settings['options'])) {
        return $settings['options'][$key];
    }

    return $default;
}

function cms_widget_is_active(string $id): bool
{
    $widget = WidgetRegistry::get($id);
    if ($widget !== null) {
        return !empty($widget['active']);
    }

    return !empty(WidgetSettingsStore::get($id)['active']);
}
