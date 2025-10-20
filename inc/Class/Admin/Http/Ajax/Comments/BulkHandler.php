<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Comments;

use Cms\Admin\Http\AjaxResponse;
use Cms\Admin\Http\Ajax\Traits\ProvidesAjaxResponses;
use Throwable;
use Core\Database\Init as DB;

final class BulkHandler
{
    use ProvidesAjaxResponses;
    use CommentsThreadHelpers;

    public function __invoke(): AjaxResponse
    {
        $action = isset($_POST['bulk_action']) ? trim((string)$_POST['bulk_action']) : '';
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));

        $redirect = $this->listUrl([
            'status' => $_POST['status'] ?? '',
            'q'      => $_POST['q'] ?? '',
            'post'   => $_POST['post'] ?? '',
            'page'   => $_POST['page'] ?? 1,
        ]);

        if ($ids === [] || $action === '') {
            return $this->errorResponse('Vyberte komentáře a požadovanou akci.', 422, null, 'warning', [
                'redirect' => $redirect,
            ]);
        }

        try {
            $existing = DB::query()->table('comments')->select(['id'])->whereIn('id', $ids)->get();
            $targetIds = [];
            foreach ($existing as $row) {
                $rowId = isset($row['id']) ? (int)$row['id'] : 0;
                if ($rowId > 0) {
                    $targetIds[] = $rowId;
                }
            }
            $targetIds = array_values(array_unique($targetIds));

            if ($targetIds === []) {
                return $this->errorResponse('Žádné platné komentáře pro hromadnou akci.', 404, null, 'warning', [
                    'redirect' => $redirect,
                ]);
            }

            $message = '';
            $affected = 0;

            switch ($action) {
                case 'published':
                case 'draft':
                case 'spam':
                    DB::query()->table('comments')
                        ->update(['status' => $action])
                        ->whereIn('id', $targetIds)
                        ->execute();
                    $affected = count($targetIds);
                    $message = match ($action) {
                        'published' => 'Komentáře byly schváleny.',
                        'spam'      => 'Komentáře byly označeny jako spam.',
                        default     => 'Komentáře byly uloženy jako koncept.',
                    };
                    break;

                case 'delete':
                    $toDelete = [];
                    foreach ($targetIds as $id) {
                        $toDelete = array_merge($toDelete, $this->collectThreadIds($id));
                    }
                    $toDelete = array_values(array_unique(array_filter($toDelete, static fn(int $id): bool => $id > 0)));

                    if ($toDelete !== []) {
                        DB::query()->table('comments')->delete()->whereIn('id', $toDelete)->execute();
                    }

                    $affected = count($toDelete);
                    $message = 'Komentáře byly odstraněny.';
                    break;

                default:
                    return $this->errorResponse('Neznámá hromadná akce.', 422, null, 'warning', [
                        'redirect' => $redirect,
                    ]);
            }
        } catch (Throwable $exception) {
            $msg = trim((string)$exception->getMessage()) ?: 'Hromadná akce selhala.';

            return $this->errorResponse($msg, 500, $msg, 'danger', [
                'redirect' => $redirect,
            ]);
        }

        if ($affected > 0) {
            $message .= ' (' . $affected . ')';
        }

        return $this->successResponse($message, [
            'redirect' => $redirect,
            'affected' => $affected,
        ]);
    }
}
