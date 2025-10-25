<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Controllers;

use Cms\Admin\Utils\AdminNavigation;
use Core\Plugins\PluginRegistry;
use Core\Plugins\PluginSettingsStore;

final class PluginsController extends BaseAdminController
{
    public function handle(string $action): void
    {
        if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->update();
            return;
        }

        $this->index();
    }

    public function index(): void
    {
        $requested = isset($_GET['plugin']) ? (string)$_GET['plugin'] : '';
        $selected = $requested !== '' ? PluginRegistry::get($requested) : null;
        $navKey = $selected ? 'plugins:' . $selected['slug'] : 'plugins';

        $this->renderAdmin('plugins/index', [
            'pageTitle'      => 'Pluginy',
            'nav'            => AdminNavigation::build($navKey),
            'plugins'        => PluginRegistry::all(),
            'selectedPlugin' => $selected,
        ]);
    }

    private function update(): void
    {
        $this->assertCsrf();

        $slug = isset($_POST['plugin']) ? (string)$_POST['plugin'] : '';
        if ($slug === '') {
            $this->redirect('admin.php?r=plugins', 'danger', 'Chybí identifikátor pluginu.');
        }

        $plugin = PluginRegistry::get($slug);
        if ($plugin === null) {
            $this->redirect('admin.php?r=plugins', 'danger', 'Plugin nebyl nalezen.');
        }

        $active = isset($_POST['active']) && (string)$_POST['active'] === '1';

        $options = [];
        if ($plugin['slug'] === 'google-analytics') {
            $measurementId = isset($_POST['measurement_id']) ? trim((string)$_POST['measurement_id']) : '';
            $options['measurement_id'] = $measurementId;
            $options['configured'] = $measurementId !== '';
        }

        $current = PluginSettingsStore::get($plugin['slug']);
        $mergedOptions = array_replace($current['options'], $options);

        PluginSettingsStore::set($plugin['slug'], $active, $mergedOptions);

        $message = 'Nastavení pluginu bylo uloženo.';
        $this->redirect('admin.php?r=plugins&plugin=' . urlencode($plugin['slug']), 'success', $message);
    }
}
