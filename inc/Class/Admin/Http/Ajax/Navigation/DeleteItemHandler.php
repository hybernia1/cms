<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Navigation;

use Cms\Admin\Http\AjaxResponse;
use Cms\Admin\Http\Ajax\Traits\ProvidesAjaxResponses;
use Core\Database\Init as DB;
use Throwable;

final class DeleteItemHandler
{
    use ProvidesAjaxResponses;
    use NavigationHelpers;

    public function __invoke(): AjaxResponse
    {
        $itemId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $menuId = isset($_POST['menu_id']) ? (int)$_POST['menu_id'] : 0;
        if ($itemId <= 0 || $menuId <= 0) {
            return $this->errorResponse('Chybí údaje položky.', 422, null, 'danger', [
                'redirect' => 'admin.php?r=navigation',
            ]);
        }

        $item = DB::query()
            ->table('navigation_items')
            ->select(['id', 'menu_id'])
            ->where('id', '=', $itemId)
            ->first();
        if (!$item || (int)$item['menu_id'] !== $menuId) {
            return $this->errorResponse('Položka nebyla nalezena.', 404, null, 'danger', [
                'redirect' => 'admin.php?r=navigation',
            ]);
        }

        try {
            DB::query()
                ->table('navigation_items')
                ->update(['parent_id' => null])
                ->where('parent_id', '=', $itemId)
                ->execute();

            DB::query()
                ->table('navigation_items')
                ->where('id', '=', $itemId)
                ->delete()
                ->execute();
        } catch (Throwable $exception) {
            $msg = $exception->getMessage() ?: 'Položku se nepodařilo odstranit.';

            return $this->errorResponse($msg, 500, null, 'danger', [
                'redirect' => 'admin.php?r=navigation&menu_id=' . $menuId,
            ]);
        }

        return $this->successResponse('Položka byla odstraněna.', [
            'redirect' => 'admin.php?r=navigation&menu_id=' . $menuId,
            'menu_id' => $menuId,
            'item_id' => $itemId,
        ]);
    }
}
