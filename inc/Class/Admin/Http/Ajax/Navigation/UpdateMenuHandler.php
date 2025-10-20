<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Navigation;

use Cms\Admin\Http\AjaxResponse;
use Cms\Admin\Http\Ajax\Traits\ProvidesAjaxResponses;
use Cms\Admin\Utils\DateTimeFactory;
use Core\Database\Init as DB;
use Throwable;

final class UpdateMenuHandler
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

        $name = isset($_POST['name']) ? trim((string)$_POST['name']) : '';
        $slugInput = isset($_POST['slug']) ? trim((string)$_POST['slug']) : '';
        $location = $this->sanitizeLocation((string)($_POST['location'] ?? 'primary'));
        $description = isset($_POST['description']) ? trim((string)$_POST['description']) : '';

        if ($name === '') {
            return $this->errorResponse('Název menu je povinný.', 422, null, 'danger', [
                'redirect' => 'admin.php?r=navigation&menu_id=' . $menuId,
            ]);
        }

        $existingLocation = $this->menuByLocation($location, $menuId);
        if ($existingLocation) {
            $label = $this->humanizeLocation($location);
            $nameExisting = (string)($existingLocation['name'] ?? '');
            $message = sprintf('Umístění „%s“ již používá menu „%s“. Nejprve uvolněte danou lokaci.', $label, $nameExisting);

            return $this->errorResponse($message, 422, null, 'danger', [
                'redirect' => 'admin.php?r=navigation&menu_id=' . $menuId,
            ]);
        }

        $slugBase = $slugInput !== '' ? $slugInput : $name;
        $slug = $this->sanitizeSlug($slugBase);
        $slug = $this->ensureUniqueSlug($slug, $menuId);

        try {
            DB::query()
                ->table('navigation_menus')
                ->update([
                    'name' => $name,
                    'slug' => $slug,
                    'location' => $location,
                    'description' => $description,
                    'updated_at' => DateTimeFactory::nowString(),
                ])
                ->where('id', '=', $menuId)
                ->execute();
        } catch (Throwable $exception) {
            $msg = $exception->getMessage() ?: 'Menu se nepodařilo upravit.';

            return $this->errorResponse($msg, 500, null, 'danger', [
                'redirect' => 'admin.php?r=navigation&menu_id=' . $menuId,
            ]);
        }

        return $this->successResponse('Menu bylo upraveno.', [
            'redirect' => 'admin.php?r=navigation&menu_id=' . $menuId,
            'menu_id' => $menuId,
        ]);
    }
}
