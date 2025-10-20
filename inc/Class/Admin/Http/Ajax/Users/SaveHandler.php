<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Users;

use Cms\Admin\Http\AjaxResponse;
use Cms\Admin\Http\Ajax\Traits\ProvidesAjaxResponses;
use Cms\Admin\Utils\DateTimeFactory;
use Core\Database\Init as DB;
use Throwable;

final class SaveHandler
{
    use ProvidesAjaxResponses;
    use UsersHelpers;

    public function __invoke(): AjaxResponse
    {
        $id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $name   = isset($_POST['name']) ? trim((string)$_POST['name']) : '';
        $email  = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
        $role   = isset($_POST['role']) ? (string)$_POST['role'] : 'user';
        $active = isset($_POST['active']) ? (int)$_POST['active'] : 1;
        $pass   = isset($_POST['password']) ? trim((string)$_POST['password']) : '';

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->errorResponse('Zadejte platné jméno a e-mail.', 422);
        }

        if ($id === 0 && $pass === '') {
            return $this->errorResponse('Zadejte heslo pro nového uživatele.', 422);
        }

        $role = in_array($role, ['admin', 'user'], true) ? $role : 'user';

        $dup = DB::query()->table('users')->select(['id'])->where('email', '=', $email);
        if ($id > 0) {
            $dup->where('id', '!=', $id);
        }
        if ($dup->first()) {
            return $this->errorResponse('Tento e-mail už používá jiný účet.', 422);
        }

        $data = [
            'name'       => $name,
            'email'      => $email,
            'role'       => $role,
            'active'     => $active,
            'updated_at' => DateTimeFactory::nowString(),
        ];

        if ($pass !== '') {
            $data['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
        }

        try {
            if ($id > 0) {
                DB::query()->table('users')->update($data)->where('id', '=', $id)->execute();
                $message = 'Uživatel upraven.';
            } else {
                $data += [
                    'created_at'   => DateTimeFactory::nowString(),
                    'token'        => null,
                    'token_expire' => null,
                ];
                DB::query()->table('users')->insertRow($data)->execute();
                $message = 'Uživatel vytvořen.';
            }
        } catch (Throwable $exception) {
            $msg = trim((string)$exception->getMessage()) ?: 'Uložení uživatele selhalo.';

            return $this->errorResponse($msg, 500);
        }

        return $this->successResponse($message, [
            'redirect' => 'admin.php?r=users',
        ]);
    }
}
