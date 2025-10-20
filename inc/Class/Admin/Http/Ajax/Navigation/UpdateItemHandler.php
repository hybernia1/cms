<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Navigation;

use Cms\Admin\Http\AjaxResponse;
use Cms\Admin\Http\Ajax\Traits\ProvidesAjaxResponses;
use Cms\Admin\Utils\DateTimeFactory;
use Core\Database\Init as DB;
use Throwable;

final class UpdateItemHandler
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
            ->select(['id', 'menu_id', 'parent_id'])
            ->where('id', '=', $itemId)
            ->first();
        if (!$item || (int)$item['menu_id'] !== $menuId) {
            return $this->errorResponse('Položka nebyla nalezena.', 404, null, 'danger', [
                'redirect' => 'admin.php?r=navigation',
            ]);
        }

        $title = isset($_POST['title']) ? trim((string)$_POST['title']) : '';
        $urlInput = isset($_POST['url']) ? trim((string)$_POST['url']) : '';
        $linkTypeInput = isset($_POST['link_type']) ? (string)$_POST['link_type'] : 'custom';
        $linkReferenceInput = isset($_POST['link_reference']) ? trim((string)$_POST['link_reference']) : '';
        $target = $this->sanitizeTarget((string)($_POST['target'] ?? '_self'));
        $cssClass = isset($_POST['css_class']) ? trim((string)$_POST['css_class']) : '';
        $parentId = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;
        $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;

        if ($title === '') {
            return $this->errorResponse('Název položky je povinný.', 422, null, 'danger', [
                'redirect' => 'admin.php?r=navigation&menu_id=' . $menuId . '&item_id=' . $itemId,
            ]);
        }

        $linkData = $this->prepareLinkData($linkTypeInput, $linkReferenceInput, $urlInput);

        if ($linkData['type'] === 'custom' && $linkData['url'] === '') {
            return $this->errorResponse('Pro vlastní odkaz musíte vyplnit URL adresu.', 422, null, 'danger', [
                'redirect' => 'admin.php?r=navigation&menu_id=' . $menuId . '&item_id=' . $itemId,
            ]);
        }

        if ($linkData['type'] !== 'custom' && !$linkData['valid']) {
            $messages = $this->linkStatusMessages();
            $reason = $linkData['reason'] ?? 'missing';
            $message = $messages[$reason] ?? 'Vybraný obsah není možné použít v navigaci.';

            return $this->errorResponse($message, 422, null, 'danger', [
                'redirect' => 'admin.php?r=navigation&menu_id=' . $menuId . '&item_id=' . $itemId,
            ]);
        }

        $itemsRaw = $this->allItemsForMenu($menuId);
        $invalidParents = $this->descendantIdsFromList($itemsRaw, $itemId);
        $invalidParents[] = $itemId;

        if ($parentId > 0) {
            if (in_array($parentId, $invalidParents, true)) {
                $parentId = 0;
            } else {
                $parent = DB::query()
                    ->table('navigation_items')
                    ->select(['id', 'menu_id'])
                    ->where('id', '=', $parentId)
                    ->first();
                if (!$parent || (int)$parent['menu_id'] !== $menuId) {
                    $parentId = 0;
                }
            }
        }

        try {
            DB::query()
                ->table('navigation_items')
                ->update([
                    'title' => $title,
                    'link_type' => $linkData['type'],
                    'link_reference' => $linkData['reference'] !== '' ? $linkData['reference'] : null,
                    'url' => $linkData['url'],
                    'target' => $target,
                    'css_class' => $cssClass !== '' ? $cssClass : null,
                    'parent_id' => $parentId > 0 ? $parentId : null,
                    'sort_order' => $sortOrder,
                    'updated_at' => DateTimeFactory::nowString(),
                ])
                ->where('id', '=', $itemId)
                ->execute();
        } catch (Throwable $exception) {
            $msg = $exception->getMessage() ?: 'Položku se nepodařilo upravit.';

            return $this->errorResponse($msg, 500, null, 'danger', [
                'redirect' => 'admin.php?r=navigation&menu_id=' . $menuId . '&item_id=' . $itemId,
            ]);
        }

        return $this->successResponse('Položka byla upravena.', [
            'redirect' => 'admin.php?r=navigation&menu_id=' . $menuId . '&item_id=' . $itemId,
            'menu_id' => $menuId,
            'item_id' => $itemId,
        ]);
    }
}
