<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Navigation;

use Cms\Admin\Http\AjaxResponse;
use Cms\Admin\Http\Ajax\Traits\ProvidesAjaxResponses;
use Cms\Admin\Utils\DateTimeFactory;
use Core\Database\Init as DB;
use Throwable;

final class CreateMenuHandler
{
    use ProvidesAjaxResponses;
    use NavigationHelpers;

    public function __invoke(): AjaxResponse
    {
        if (!$this->tablesReady()) {
            return $this->errorResponse('Tabulky navigace nejsou k dispozici.', 500, null, 'danger', [
                'redirect' => 'admin.php?r=navigation',
            ]);
        }

        $name = isset($_POST['name']) ? trim((string)$_POST['name']) : '';
        $slugInput = isset($_POST['slug']) ? trim((string)$_POST['slug']) : '';
        $location = $this->sanitizeLocation((string)($_POST['location'] ?? 'primary'));
        $description = isset($_POST['description']) ? trim((string)$_POST['description']) : '';

        if ($name === '') {
            return $this->errorResponse('Název menu je povinný.', 422, null, 'danger', [
                'redirect' => 'admin.php?r=navigation',
            ]);
        }

        $existingLocation = $this->menuByLocation($location);
        if ($existingLocation) {
            $label = $this->humanizeLocation($location);
            $nameExisting = (string)($existingLocation['name'] ?? '');
            $message = sprintf('Umístění „%s“ již používá menu „%s“. Nejprve změňte nebo odeberte existující menu.', $label, $nameExisting);

            return $this->errorResponse($message, 422, null, 'danger', [
                'redirect' => 'admin.php?r=navigation&menu_id=' . (int)($existingLocation['id'] ?? 0),
            ]);
        }

        $slugBase = $slugInput !== '' ? $slugInput : $name;
        $slug = $this->sanitizeSlug($slugBase);
        $slug = $this->ensureUniqueSlug($slug, null);

        try {
            $menuId = (int) DB::query()
                ->table('navigation_menus')
                ->insert([
                    'slug' => $slug,
                    'name' => $name,
                    'location' => $location,
                    'description' => $description,
                    'created_at' => DateTimeFactory::nowString(),
                    'updated_at' => DateTimeFactory::nowString(),
                ])
                ->insertGetId();
        } catch (Throwable $exception) {
            $msg = $exception->getMessage() ?: 'Menu se nepodařilo vytvořit.';

            return $this->errorResponse($msg, 500);
        }

        return $this->successResponse('Menu bylo vytvořeno.', [
            'redirect' => 'admin.php?r=navigation&menu_id=' . $menuId,
            'menu_id' => $menuId,
        ]);
    }
}
