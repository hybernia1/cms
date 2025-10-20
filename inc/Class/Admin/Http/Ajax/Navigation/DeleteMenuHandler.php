<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Navigation;

use Cms\Admin\Http\AjaxResponse;
use Cms\Admin\Http\Ajax\Traits\ProvidesAjaxResponses;
use Core\Database\Init as DB;
use Throwable;

final class DeleteMenuHandler
{
    use ProvidesAjaxResponses;
    use NavigationHelpers;

    public function __invoke(): AjaxResponse
    {
        $menuId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($menuId <= 0) {
            return $this->errorResponse('Chybí ID menu.', 422, null, 'danger', [
                'redirect' => 'admin.php?r=navigation',
            ]);
        }

        $menu = $this->findMenu($menuId);
        if (!$menu) {
            return $this->errorResponse('Menu nebylo nalezeno.', 404, null, 'danger', [
                'redirect' => 'admin.php?r=navigation',
            ]);
        }

        try {
            DB::query()->table('navigation_items')->where('menu_id', '=', $menuId)->delete()->execute();
            DB::query()->table('navigation_menus')->where('id', '=', $menuId)->delete()->execute();
        } catch (Throwable $exception) {
            $msg = $exception->getMessage() ?: 'Menu se nepodařilo odstranit.';

            return $this->errorResponse($msg, 500);
        }

        return $this->successResponse('Menu bylo smazáno.', [
            'redirect' => 'admin.php?r=navigation',
            'deleted_menu_id' => $menuId,
        ]);
    }
}
