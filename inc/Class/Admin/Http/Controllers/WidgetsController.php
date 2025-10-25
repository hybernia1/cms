<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Controllers;

use Cms\Admin\Utils\AdminNavigation;
use Core\Widgets\WidgetRegistry;
use Core\Widgets\WidgetSettingsStore;

final class WidgetsController extends BaseAdminController
{
    public function handle(string $action): void
    {
        if ($action === 'toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->toggle();
            return;
        }

        $this->index();
    }

    public function index(): void
    {
        $requested = isset($_GET['widget']) ? (string)$_GET['widget'] : '';
        $widgets = WidgetRegistry::all();
        $selected = null;

        if ($requested !== '') {
            $selected = WidgetRegistry::get($requested);
        }

        if ($selected === null && $widgets !== []) {
            $selected = $widgets[0];
        }

        $navKey = $selected ? 'widgets:' . $selected['id'] : 'widgets';

        $this->renderAdmin('widgets/index', [
            'pageTitle'      => 'Widgety',
            'nav'            => AdminNavigation::build($navKey),
            'widgets'        => $widgets,
            'selectedWidget' => $selected,
        ]);
    }

    private function toggle(): void
    {
        $this->assertCsrf();

        $requested = isset($_POST['widget']) ? (string)$_POST['widget'] : '';
        if ($requested === '') {
            $this->redirect('admin.php?r=widgets', 'danger', 'Chybí identifikátor widgetu.');
        }

        $widget = WidgetRegistry::get($requested);
        if ($widget === null) {
            $this->redirect('admin.php?r=widgets', 'danger', 'Widget nebyl nalezen.');
        }

        $active = isset($_POST['active']) && (string)$_POST['active'] === '1';
        WidgetSettingsStore::set($widget['id'], $active);

        $message = $active ? 'Widget byl aktivován.' : 'Widget byl deaktivován.';
        $this->redirect('admin.php?r=widgets&widget=' . urlencode($widget['id']), 'success', $message);
    }
}
