<?php
declare(strict_types=1);

use Core\Plugins\PluginRegistry;
use Core\Plugins\PluginSettingsStore;

function cms_plugins_dir(): string
{
    return defined('BASE_DIR') ? BASE_DIR . '/plugins' : __DIR__ . '/../..' . '/plugins';
}

/**
 * Load plugin bootstrap files and trigger registration hook.
 */
function cms_bootstrap_plugins(): void
{
    $directory = cms_plugins_dir();
    if (!is_dir($directory)) {
        return;
    }

    $files = [];

    foreach (glob($directory . '/*/plugin.php') ?: [] as $file) {
        $files[] = $file;
    }

    foreach (glob($directory . '/*.php') ?: [] as $file) {
        if (basename($file) === 'plugin.php') {
            $files[] = $file;
        }
    }

    sort($files);

    foreach ($files as $file) {
        require_once $file;
    }

    cms_do_action('cms_register_plugins', PluginRegistry::class);
}

/**
 * Convenience helper to check if any plugin has been registered.
 */
function cms_has_plugins(): bool
{
    return PluginRegistry::all() !== [];
}

/**
 * @return array{active:bool,options:array<string,mixed>}
 */
function cms_plugin_settings(string $slug): array
{
    return PluginSettingsStore::get($slug);
}

function cms_plugin_option(string $slug, string $key, mixed $default = null): mixed
{
    $settings = PluginSettingsStore::get($slug);
    if (array_key_exists($key, $settings['options'])) {
        return $settings['options'][$key];
    }

    return $default;
}

function cms_plugin_is_active(string $slug): bool
{
    $plugin = PluginRegistry::get($slug);
    if ($plugin !== null) {
        return !empty($plugin['active']);
    }

    return !empty(PluginSettingsStore::get($slug)['active']);
}
