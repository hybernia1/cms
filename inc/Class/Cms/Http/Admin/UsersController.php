<?php
declare(strict_types=1);

namespace Cms\Http\Admin;

use Cms\View\ViewEngine;
use Cms\Auth\AuthService;
use Core\Database\Init as DB;

final class UsersController
{
    private ViewEngine $view;
    private AuthService $auth;

    public function __construct(string $baseViewsPath)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $this->view = new ViewEngine($baseViewsPath);
        $this->auth = new AuthService();
    }

    public function handle(string $action): void
    {
        switch ($action) {
            case 'edit':    $this->edit(); return;
            case 'save':    $this->save(); return;
            case 'index':
            default:        $this->index(); return;
        }
    }

    private function nav(): array
    {
        return [
            ['key'=>'dashboard','label'=>'Dashboard','href'=>'admin.php?r=dashboard','active'=>false],
            ['key'=>'posts:post','label'=>'Příspěvky','href'=>'admin.php?r=posts&type=post','active'=>false],
            ['key'=>'media','label'=>'Média','href'=>'admin.php?r=media','active'=>false],
            ['key'=>'terms','label'=>'Termy','href'=>'admin.php?r=terms','active'=>false],
            ['key'=>'comments','label'=>'Komentáře','href'=>'admin.php?r=comments','active'=>false],
            ['key'=>'users','label'=>'Uživatelé','href'=>'admin.php?r=users','active'=>true],
            ['key'=>'themes','label'=>'Šablony','href'=>'admin.php?r=themes','active'=>false],
            ['key'=>'settings','label'=>'Nastavení','href'=>'admin.php?r=settings','active'=>false],
            ['key'=>'migrations','label'=>'Migrace','href'=>'admin.php?r=migrations','active'=>false],
        ];
    }

    private function token(): string
    {
        if (empty($_SESSION['csrf_admin'])) $_SESSION['csrf_admin'] = bin2hex(random_bytes(16));
        return $_SESSION['csrf_admin'];
    }
    private function assertCsrf(): void
    {
        if (empty($_SESSION['csrf_admin']) || !hash_equals($_SESSION['csrf_admin'], (string)($_POST['csrf'] ?? ''))) {
            http_response_code(419); echo 'CSRF token invalid'; exit;
        }
    }
    private function flash(string $type, string $msg): void
    {
        $_SESSION['_flash'] = ['type'=>$type,'msg'=>$msg];
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

        $this->view->render('users/index', [
            'pageTitle'   => 'Uživatelé',
            'nav'         => $this->nav(),
            'currentUser' => $this->auth->user(),
            'flash'       => $_SESSION['_flash'] ?? null,
            'data'        => $data,
            'q'           => $q,
            'csrf'        => $this->token(),
        ]);
        unset($_SESSION['_flash']);
    }

    private function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $user = $id ? DB::query()->table('users')->select(['*'])->where('id','=', $id)->first() : null;

        $this->view->render('users/edit', [
            'pageTitle'   => $id ? 'Upravit uživatele' : 'Nový uživatel',
            'nav'         => $this->nav(),
            'currentUser' => $this->auth->user(),
            'flash'       => $_SESSION['_flash'] ?? null,
            'user'        => $user,
            'csrf'        => $this->token(),
        ]);
        unset($_SESSION['_flash']);
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
        $this->flash('danger','Zadejte platné jméno a e-mail.');
        header('Location: admin.php?r=users&a=edit'.($id?"&id={$id}":'')); exit;
    }

    // unikátní e-mail
    $dup = DB::query()->table('users')->select(['id'])->where('email','=', $email);
    if ($id) $dup->where('id','!=',$id);
    if ($dup->first()) {
        $this->flash('danger','Tento e-mail už používá jiný účet.');
        header('Location: admin.php?r=users&a=edit'.($id?"&id={$id}":'')); exit;
    }

    $data = [
        'name'       => $name,
        'email'      => $email,
        'role'       => in_array($role,['admin','user'],true)?$role:'user',
        'active'     => $active,
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    if ($pass !== '') {
        $data['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
    }

    if ($id) {
        DB::query()->table('users')->update($data)->where('id','=', $id)->execute();
        $this->flash('success','Uživatel upraven.');
    } else {
        $data += [
            'created_at'   => date('Y-m-d H:i:s'),
            'token'        => null,
            'token_expire' => null,
        ];
        DB::query()->table('users')->insertRow($data)->execute();
        $this->flash('success','Uživatel vytvořen.');
    }

    header('Location: admin.php?r=users'); exit;
}
}
