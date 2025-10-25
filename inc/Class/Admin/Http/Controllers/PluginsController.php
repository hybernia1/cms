<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Controllers;

use Cms\Admin\Utils\AdminNavigation;
use Core\Plugins\PluginRegistry;

final class PluginsController extends BaseAdminController
{
    public function handle(string $action): void
    {
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
}
