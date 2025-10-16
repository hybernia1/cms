<?php
declare(strict_types=1);

namespace Cms\Http\Admin;

use Core\Database\Init as DB;
use Cms\Utils\AdminNavigation;

final class UsersController extends BaseAdminController
{
    public function handle(string $action): void
    {
        switch ($action) {
            case 'edit':    $this->edit(); return;
            case 'save':    $this->save(); return;
            case 'index':
            default:        $this->index(); return;
        }
    }


    private function index(): void
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $q = trim((string)($_GET['q'] ?? ''));
        $b = DB::query()->table('users','u')->select(['u.id','u.name','u.email','u.role','u.active','u.created_at']);
        if ($q !== '') {
            $like = "%{$q}%";
            $b->where(function($w) use($like){
                $w->whereLike('u.name', $like)
                  ->orWhere('u.email','LIKE', $like);
            });
        }
        $b->orderBy('u.created_at','DESC');
        $data = $b->paginate($page, 20);

        $this->renderAdmin('users/index', [
            'pageTitle' => 'Uživatelé',
            'nav'       => AdminNavigation::build('users'),
            'data'      => $data,
            'q'         => $q,
        ]);
    }

    private function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $user = $id ? DB::query()->table('users')->select(['*'])->where('id','=', $id)->first() : null;

        $this->renderAdmin('users/edit', [
            'pageTitle' => $id ? 'Upravit uživatele' : 'Nový uživatel',
            'nav'       => AdminNavigation::build('users'),
            'user'      => $user,
        ]);
    }

    private function save(): void
    {
        $this->assertCsrf();
        $id     = (int)($_POST['id'] ?? 0);
        $name   = trim((string)($_POST['name'] ?? ''));
        $email  = trim((string)($_POST['email'] ?? ''));
        $role   = (string)($_POST['role'] ?? 'user');
        $active = (int)($_POST['active'] ?? 1);
        $pass   = (string)($_POST['password'] ?? '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->redirect(
                'admin.php?r=users&a=edit' . ($id ? "&id={$id}" : ''),
                'danger',
                'Zadejte platné jméno a e-mail.'
            );
        }

        $dup = DB::query()->table('users')->select(['id'])->where('email','=', $email);
        if ($id) {
            $dup->where('id','!=',$id);
        }
        if ($dup->first()) {
            $this->redirect(
                'admin.php?r=users&a=edit' . ($id ? "&id={$id}" : ''),
                'danger',
                'Tento e-mail už používá jiný účet.'
            );
        }

        $data = [
            'name'       => $name,
            'email'      => $email,
            'role'       => in_array($role, ['admin','user'], true) ? $role : 'user',
            'active'     => $active,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($pass !== '') {
            $data['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
        }

        if ($id) {
            DB::query()->table('users')->update($data)->where('id','=', $id)->execute();
            $this->redirect('admin.php?r=users', 'success', 'Uživatel upraven.');
        } else {
            $data += [
                'created_at'   => date('Y-m-d H:i:s'),
                'token'        => null,
                'token_expire' => null,
            ];
            DB::query()->table('users')->insertRow($data)->execute();
            $this->redirect('admin.php?r=users', 'success', 'Uživatel vytvořen.');
        }
    }
}
