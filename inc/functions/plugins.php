<?php
declare(strict_types=1);

use Core\Plugins\PluginRegistry;

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
