<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Users;

use Cms\Admin\Auth\AuthService;
use Cms\Admin\Http\AjaxResponse;
use Cms\Admin\Http\Ajax\Traits\ProvidesAjaxResponses;
use Core\Database\Init as DB;
use Throwable;

final class DeleteHandler
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
        $userId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $sendMail = isset($_POST['send_email']) ? (int)$_POST['send_email'] === 1 : false;
        $q = isset($_POST['q']) ? trim((string)$_POST['q']) : '';
        $page = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
        $redirect = $this->listUrl($q, $page);

        if ($userId <= 0) {
            return $this->errorResponse('Neplatný uživatel.', 422, null, 'warning', [
                'redirect' => $redirect,
            ]);
        }

        $user = DB::query()->table('users')->select(['id', 'role', 'email', 'name'])->where('id', '=', $userId)->first();
        if (!$user) {
            return $this->errorResponse('Uživatel nenalezen.', 404, null, 'danger', [
                'redirect' => $redirect,
            ]);
        }

        $currentUserId = (int)($this->auth->user()['id'] ?? 0);
        $role = (string)($user['role'] ?? 'user');
        if ($role === 'admin' || $userId === $currentUserId) {
            return $this->errorResponse('Tento účet nelze odstranit.', 422, null, 'warning', [
                'redirect' => $redirect,
            ]);
        }

        try {
            DB::query()->table('users')->delete()->where('id', '=', $userId)->execute();
        } catch (Throwable $exception) {
            $msg = trim((string)$exception->getMessage()) ?: 'Uživatele se nepodařilo odstranit.';

            return $this->errorResponse($msg, 500, $msg, 'danger', [
                'redirect' => $redirect,
            ]);
        }

        if ($sendMail) {
            $this->notifyUser($user, 'user_account_deleted');
        }

        return $this->successResponse('Uživatel byl odstraněn.', [
            'redirect' => $redirect,
            'deleted'  => [$userId],
        ]);
    }
}
