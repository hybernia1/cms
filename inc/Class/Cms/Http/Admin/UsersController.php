<?php
declare(strict_types=1);

namespace Cms\Http\Admin;

use Core\Database\Init as DB;
use Cms\Mail\MailService;
use Cms\Mail\TemplateManager;
use Cms\Settings\CmsSettings;
use Cms\Utils\AdminNavigation;
use Cms\Utils\DateTimeFactory;

final class UsersController extends BaseAdminController
{
    public function handle(string $action): void
    {
        switch ($action) {
            case 'edit':          $this->edit(); return;
            case 'save':          $this->save(); return;
            case 'bulk':          $this->bulk(); return;
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

        $settings = new CmsSettings();
        $items = [];
        foreach (($paginated['items'] ?? []) as $row) {
            $created = DateTimeFactory::fromStorage(isset($row['created_at']) ? (string)$row['created_at'] : null);
            $row['created_at_raw'] = isset($row['created_at']) ? (string)$row['created_at'] : '';
            if ($created) {
                $row['created_at_display'] = $settings->formatDateTime($created);
            } else {
                $row['created_at_display'] = $row['created_at_raw'];
            }
            $items[] = $row;
        }

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

        try {
            $template = $templateManager->render($templateKey, [
                'siteTitle' => $settings->siteTitle(),
                'userName'  => (string)($user['name'] ?? ''),
                'userEmail' => $email,
                'user'      => $user,
            ]);
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
