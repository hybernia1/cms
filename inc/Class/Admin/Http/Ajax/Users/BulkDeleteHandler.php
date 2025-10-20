<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Users;

use Cms\Admin\Auth\AuthService;
use Cms\Admin\Http\AjaxResponse;
use Cms\Admin\Http\Ajax\Traits\ProvidesAjaxResponses;
use Core\Database\Init as DB;
use Throwable;

final class BulkDeleteHandler
{
    use ProvidesAjaxResponses;
    use UsersHelpers;

    private AuthService $auth;

    public function __construct(?AuthService $auth = null)
    {
        $this->auth = $auth ?? new AuthService();
    }

    public function __invoke(): AjaxResponse
    {
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));

        $q = isset($_POST['q']) ? trim((string)$_POST['q']) : '';
        $page = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
        $redirect = $this->listUrl($q, $page);

        if ($ids === []) {
            return $this->errorResponse('Vyberte uživatele k odstranění.', 422, null, 'warning', [
                'redirect' => $redirect,
            ]);
        }

        $rows = DB::query()->table('users')->select(['id', 'role'])->whereIn('id', $ids)->get();
        $currentUserId = (int)($this->auth->user()['id'] ?? 0);
        $targetIds = [];
        foreach ($rows as $row) {
            $rowId = isset($row['id']) ? (int)$row['id'] : 0;
            $role = isset($row['role']) ? (string)$row['role'] : 'user';
            if ($rowId <= 0 || $role === 'admin' || $rowId === $currentUserId) {
                continue;
            }
            $targetIds[] = $rowId;
        }
        $targetIds = array_values(array_unique($targetIds));

        if ($targetIds === []) {
            return $this->errorResponse('Žádní vybraní uživatelé pro smazání.', 422, null, 'warning', [
                'redirect' => $redirect,
            ]);
        }

        try {
            DB::query()->table('users')->delete()->whereIn('id', $targetIds)->execute();
        } catch (Throwable $exception) {
            $msg = trim((string)$exception->getMessage()) ?: 'Uživatele se nepodařilo odstranit.';

            return $this->errorResponse($msg, 500, $msg, 'danger', [
                'redirect' => $redirect,
            ]);
        }

        $message = 'Uživatelé byli odstraněni. (' . count($targetIds) . ')';

        return $this->successResponse($message, [
            'redirect' => $redirect,
            'deleted'  => $targetIds,
        ]);
    }
}
