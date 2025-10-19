<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Controllers;

use Core\Database\Init as DB;
use Cms\Admin\Mail\MailService;
use Cms\Admin\Mail\TemplateManager;
use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Utils\AdminNavigation;
use Cms\Admin\Utils\DateTimeFactory;
use Throwable;

final class UsersController extends BaseAdminController
{
    public function handle(string $action): void
    {
        switch ($action) {
            case 'edit':          $this->edit(); return;
            case 'save':          $this->save(); return;
            case 'bulk':          $this->bulk(); return;
            case 'delete':        $this->deleteSingle(); return;
            case 'toggle':        $this->toggle(); return;
            case 'send-template': $this->sendTemplate(); return;
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
        $paginated = $b->paginate($page, 20);

        $pagination = $this->paginationData($paginated, $page, 20);
        $buildUrl = $this->listingUrlBuilder([
            'r' => 'users',
            'q' => $q,
        ]);

        $items = $this->normalizeCreatedAt($paginated['items'] ?? []);

        $this->renderAdmin('users/index', [
            'pageTitle' => 'Uživatelé',
            'nav'       => AdminNavigation::build('users'),
            'items'     => $items,
            'pagination'=> $pagination,
            'searchQuery' => $q,
            'buildUrl'  => $buildUrl,
        ]);
    }

    private function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $user = $id ? DB::query()->table('users')->select(['*'])->where('id','=', $id)->first() : null;

        $templateManager = new TemplateManager();
        $settings = new CmsSettings();
        $mailTemplates = [];
        foreach ($templateManager->availableKeys() as $key) {
            $label = $key;
            try {
                $template = $templateManager->render($key, [
                    'siteTitle' => $settings->siteTitle(),
                    'userName'  => (string)($user['name'] ?? ''),
                    'userEmail' => (string)($user['email'] ?? ''),
                    'user'      => $user,
                ]);
                $label = $template->subject();
            } catch (\Throwable $e) {
                // keep default label
            }

            $mailTemplates[] = [
                'key'   => $key,
                'label' => $label,
            ];
        }

        $this->renderAdmin('users/edit', [
            'pageTitle'     => $id ? 'Upravit uživatele' : 'Nový uživatel',
            'nav'           => AdminNavigation::build('users'),
            'user'          => $user,
            'mailTemplates' => $mailTemplates,
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
        $pass   = trim((string)($_POST['password'] ?? ''));

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->redirect(
                'admin.php?r=users&a=edit' . ($id ? "&id={$id}" : ''),
                'danger',
                'Zadejte platné jméno a e-mail.'
            );
        }

        if ($id === 0 && $pass === '') {
            $this->redirect(
                'admin.php?r=users&a=edit',
                'danger',
                'Zadejte heslo pro nového uživatele.'
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
            'updated_at' => DateTimeFactory::nowString(),
        ];
        if ($pass !== '') {
            $data['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
        }

        if ($id) {
            DB::query()->table('users')->update($data)->where('id','=', $id)->execute();
            $this->redirect('admin.php?r=users', 'success', 'Uživatel upraven.');
        } else {
            $data += [
                'created_at'   => DateTimeFactory::nowString(),
                'token'        => null,
                'token_expire' => null,
            ];
            DB::query()->table('users')->insertRow($data)->execute();
            $this->redirect('admin.php?r=users', 'success', 'Uživatel vytvořen.');
        }
    }

    private function bulk(): void
    {
        $this->assertCsrf();

        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));

        $q = trim((string)($_POST['q'] ?? ''));
        $page = max(1, (int)($_POST['page'] ?? 1));
        $redirect = $this->listUrl($q, $page);

        if ($ids === []) {
            $this->redirect($redirect, 'warning', 'Vyberte uživatele k odstranění.');
        }

        $rows = DB::query()->table('users')
            ->select(['id','role'])
            ->whereIn('id', $ids)
            ->get();

        $currentUserId = (int)($this->auth->user()['id'] ?? 0);
        $targetIds = [];
        foreach ($rows as $row) {
            $rowId = (int)($row['id'] ?? 0);
            $role = (string)($row['role'] ?? 'user');
            if ($rowId <= 0) {
                continue;
            }
            if ($role === 'admin' || $rowId === $currentUserId) {
                continue;
            }
            $targetIds[] = $rowId;
        }
        $targetIds = array_values(array_unique($targetIds));

        if ($targetIds === []) {
            $this->redirect($redirect, 'warning', 'Žádní vybraní uživatelé pro smazání.');
        }

        try {
            DB::query()->table('users')
                ->delete()
                ->whereIn('id', $targetIds)
                ->execute();
        } catch (\Throwable $e) {
            $this->redirect($redirect, 'danger', $e->getMessage());
        }

        $this->redirect($redirect, 'success', 'Uživatelé byli odstraněni. (' . count($targetIds) . ')');
    }

    private function deleteSingle(): void
    {
        $this->assertCsrf();

        $userId = (int)($_POST['id'] ?? 0);
        $sendMail = (int)($_POST['send_email'] ?? 0) === 1;
        $q = trim((string)($_POST['q'] ?? ''));
        $page = max(1, (int)($_POST['page'] ?? 1));
        $redirect = $this->listUrl($q, $page);

        if ($userId <= 0) {
            $this->redirect($redirect, 'warning', 'Neplatný uživatel.');
        }

        $user = DB::query()->table('users')->select(['id','role','email','name'])->where('id','=', $userId)->first();
        if (!$user) {
            $this->redirect($redirect, 'danger', 'Uživatel nenalezen.');
        }

        $currentUserId = (int)($this->auth->user()['id'] ?? 0);
        $role = (string)($user['role'] ?? 'user');
        if ($role === 'admin' || $userId === $currentUserId) {
            $this->redirect($redirect, 'warning', 'Tento účet nelze odstranit.');
        }

        try {
            DB::query()->table('users')->delete()->where('id','=', $userId)->execute();
        } catch (\Throwable $e) {
            $this->redirect($redirect, 'danger', $e->getMessage());
        }

        if ($sendMail) {
            $this->notifyUser($user, 'user_account_deleted');
        }

        $this->redirect($redirect, 'success', 'Uživatel byl odstraněn.');
    }

    private function toggle(): void
    {
        $this->assertCsrf();

        $userId = (int)($_POST['id'] ?? 0);
        $status = (int)($_POST['status'] ?? 0) === 1 ? 1 : 0;
        $sendMail = (int)($_POST['send_email'] ?? 0) === 1;
        $q = trim((string)($_POST['q'] ?? ''));
        $page = max(1, (int)($_POST['page'] ?? 1));
        $redirect = $this->listUrl($q, $page);

        if ($userId <= 0) {
            $this->redirect($redirect, 'warning', 'Neplatný uživatel.');
        }

        $user = DB::query()->table('users')->select(['id','role','email','name','active'])->where('id','=', $userId)->first();
        if (!$user) {
            $this->redirect($redirect, 'danger', 'Uživatel nenalezen.');
        }

        $currentUserId = (int)($this->auth->user()['id'] ?? 0);
        $role = (string)($user['role'] ?? 'user');
        if ($role === 'admin' || $userId === $currentUserId) {
            $this->redirect($redirect, 'warning', 'Tento účet nelze upravit.');
        }

        $currentActive = (int)($user['active'] ?? 0);
        if ($currentActive === $status) {
            $this->redirect(
                $redirect,
                'info',
                $status === 1 ? 'Uživatel je již aktivní.' : 'Uživatel je již neaktivní.'
            );
        }

        try {
            DB::query()->table('users')->update([
                'active'     => $status,
                'updated_at' => DateTimeFactory::nowString(),
            ])->where('id','=', $userId)->execute();
        } catch (\Throwable $e) {
            $this->redirect($redirect, 'danger', $e->getMessage());
        }

        if ($sendMail) {
            $template = $status === 1 ? 'user_account_activated' : 'user_account_deactivated';
            $this->notifyUser($user, $template);
        }

        $this->redirect(
            $redirect,
            'success',
            $status === 1 ? 'Uživatel byl aktivován.' : 'Uživatel byl deaktivován.'
        );
    }

    private function sendTemplate(): void
    {
        $this->assertCsrf();

        $userId = (int)($_POST['id'] ?? 0);
        $templateKey = trim((string)($_POST['template'] ?? ''));

        if ($userId <= 0 || $templateKey === '') {
            $this->redirect('admin.php?r=users', 'danger', 'Vyberte uživatele a šablonu.');
        }

        $user = DB::query()->table('users')->select(['*'])->where('id','=', $userId)->first();
        if (!$user) {
            $this->redirect('admin.php?r=users', 'danger', 'Uživatel nenalezen.');
        }

        $email = (string)($user['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->redirect('admin.php?r=users&a=edit&id=' . $userId, 'danger', 'Uživatel nemá platný e-mail.');
        }

        $settings = new CmsSettings();
        $templateManager = new TemplateManager();

        $templateData = [
            'siteTitle' => $settings->siteTitle(),
            'userName'  => (string)($user['name'] ?? ''),
            'userEmail' => $email,
            'user'      => $user,
        ];

        if ($templateKey === 'lost_password') {
            try {
                $token = bin2hex(random_bytes(20));
            } catch (\Throwable $e) {
                $this->redirect('admin.php?r=users&a=edit&id=' . $userId, 'danger', 'Nepodařilo se vygenerovat resetovací odkaz.');
            }

            $expiresAt = DateTimeFactory::now()->modify('+1 hour')->format('Y-m-d H:i:s');

            try {
                DB::query()->table('users')->update([
                    'token'        => $token,
                    'token_expire' => $expiresAt,
                    'updated_at'   => DateTimeFactory::nowString(),
                ])->where('id','=', $userId)->execute();
            } catch (\Throwable $e) {
                $this->redirect('admin.php?r=users&a=edit&id=' . $userId, 'danger', 'Nepodařilo se uložit resetovací token.');
            }

            $user['token'] = $token;
            $user['token_expire'] = $expiresAt;
            $templateData['user'] = $user;

            $baseUrl = rtrim($settings->siteUrl(), '/');
            $templateData['resetUrl'] = $baseUrl . '/reset?token=' . urlencode($token);
        }

        try {
            $template = $templateManager->render($templateKey, $templateData);
        } catch (\Throwable $e) {
            $this->redirect('admin.php?r=users&a=edit&id=' . $userId, 'danger', 'Šablonu se nepodařilo načíst.');
        }

        $mailService = new MailService($settings);
        $ok = $mailService->sendTemplate($email, $template, (string)($user['name'] ?? '') ?: null);

        if ($ok) {
            $this->redirect('admin.php?r=users&a=edit&id=' . $userId, 'success', 'E-mail byl odeslán.');
        }

        $this->redirect('admin.php?r=users&a=edit&id=' . $userId, 'danger', 'E-mail se nepodařilo odeslat.');
    }

    /**
     * @param array<string,mixed> $user
     * @param array<string,mixed> $extra
     */
    private function notifyUser(array $user, string $templateKey, array $extra = []): void
    {
        $email = (string)($user['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $settings = new CmsSettings();
        $data = array_merge([
            'siteTitle' => $settings->siteTitle(),
            'userName'  => (string)($user['name'] ?? ''),
            'userEmail' => $email,
            'loginUrl'  => $this->loginUrl($settings),
        ], $extra);

        $manager = new TemplateManager();

        try {
            $template = $manager->render($templateKey, $data);
        } catch (Throwable) {
            return;
        }

        try {
            (new MailService($settings))->sendTemplate($email, $template, (string)($user['name'] ?? '') ?: null);
        } catch (Throwable) {
            // ignore mailing errors
        }
    }

    private function loginUrl(CmsSettings $settings): string
    {
        $base = rtrim($settings->siteUrl(), '/');
        $path = $settings->seoUrlsEnabled() ? '/login' : '/index.php?r=login';
        return $base . $path;
    }

    private function listUrl(string $q, int $page): string
    {
        $query = ['r' => 'users'];
        if ($q !== '') {
            $query['q'] = $q;
        }
        if ($page > 1) {
            $query['page'] = $page;
        }

        $qs = http_build_query($query);

        return $qs === '' ? 'admin.php?r=users' : 'admin.php?' . $qs;
    }
}
