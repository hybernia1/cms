<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Controllers;

use Core\Database\Init as DB;
use Cms\Admin\Domain\Services\UserSlugService;
use Cms\Admin\Mail\MailService;
use Cms\Admin\Mail\TemplateManager;
use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Utils\AdminNavigation;
use Cms\Admin\Utils\DateTimeFactory;
use Cms\Admin\Utils\LinkGenerator;
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

        $listing = $this->prepareListing($q, $page);

        if ($this->wantsJsonIndex()) {
            $this->jsonResponse($this->listingJsonPayload($listing));
        }

        $this->renderAdmin('users/index', [
            'pageTitle'   => 'Uživatelé',
            'nav'         => AdminNavigation::build('users'),
            'items'       => $listing['items'],
            'pagination'  => $listing['pagination'],
            'searchQuery' => $listing['searchQuery'],
            'buildUrl'    => $listing['buildUrl'],
            'currentUrl'  => $listing['currentUrl'],
        ]);
    }

    private function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $user = $id ? DB::query()->table('users')->select(['*'])->where('id','=', $id)->first() : null;

        $profileUrl = null;
        if (is_array($user)) {
            $links = new LinkGenerator();
            $slug = isset($user['slug']) ? (string)$user['slug'] : '';
            $profileUrl = $links->user($slug !== '' ? $slug : null, $id > 0 ? $id : null);
        }

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
            'profileUrl'    => $profileUrl,
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
        $website = trim((string)($_POST['website_url'] ?? ''));
        $bioInput = trim((string)($_POST['bio'] ?? ''));

        $errors = [];
        if ($name === '') {
            $errors['name'][] = 'Zadejte jméno.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Zadejte platný e-mail.';
        }
        $websiteNormalized = '';
        if ($website !== '') {
            $normalizedWebsite = $this->normalizeWebsiteUrl($website);
            if ($normalizedWebsite === null) {
                $errors['website_url'][] = 'Zadejte platnou URL adresu.';
            } else {
                if (strlen($normalizedWebsite) > 255) {
                    $errors['website_url'][] = 'URL je příliš dlouhá.';
                }
                $websiteNormalized = $normalizedWebsite;
            }
        }

        $bioNormalized = '';
        if ($bioInput !== '') {
            $bioNormalized = $this->sanitizeBio($bioInput);
            if ($bioNormalized !== '') {
                $length = function_exists('mb_strlen') ? mb_strlen($bioNormalized, 'UTF-8') : strlen($bioNormalized);
                if ($length > 600) {
                    $errors['bio'][] = 'Bio může mít maximálně 600 znaků.';
                }
            }
        }
        if ($errors !== []) {
            $this->respondValidationErrors($errors, 'Zadejte platné jméno a e-mail.', 'admin.php?r=users&a=edit' . ($id ? '&id=' . $id : ''));
        }

        if ($id === 0 && $pass === '') {
            $this->respondValidationErrors([
                'password' => ['Zadejte heslo pro nového uživatele.'],
            ], 'Zadejte heslo pro nového uživatele.', 'admin.php?r=users&a=edit');
        }

        $dup = DB::query()->table('users')->select(['id'])->where('email','=', $email);
        if ($id) {
            $dup->where('id','!=',$id);
        }
        if ($dup->first()) {
            $this->respondValidationErrors([
                'email' => ['Tento e-mail už používá jiný účet.'],
            ], 'Tento e-mail už používá jiný účet.', 'admin.php?r=users&a=edit' . ($id ? '&id=' . $id : ''));
        }

        $slugService = new UserSlugService();
        $slug = $slugService->generate($name, $id > 0 ? $id : null);

        $data = [
            'name'       => $name,
            'slug'       => $slug,
            'email'      => $email,
            'role'       => in_array($role, ['admin','user'], true) ? $role : 'user',
            'active'     => $active,
            'updated_at' => DateTimeFactory::nowString(),
            'website_url' => $websiteNormalized !== '' ? $websiteNormalized : null,
            'bio'        => $bioNormalized !== '' ? $bioNormalized : null,
        ];
        if ($pass !== '') {
            $data['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
        }

        if ($id) {
            try {
                DB::query()->table('users')->update($data)->where('id','=', $id)->execute();
            } catch (Throwable $e) {
                $this->respondFailure('Uživatele se nepodařilo uložit.', 'admin.php?r=users&a=edit&id=' . $id, $e);
            }

            if ($this->isAjax()) {
                $payload = [
                    'success' => true,
                    'message' => 'Uživatel upraven.',
                    'user'    => $this->findUserRow($id),
                ];
                $this->jsonResponse($payload);
            }

            $this->redirect('admin.php?r=users', 'success', 'Uživatel upraven.');
        }

        $data += [
            'created_at'   => DateTimeFactory::nowString(),
            'token'        => null,
            'token_expire' => null,
        ];

        $newId = 0;
        try {
            $query = DB::query();
            $query->table('users')->insertRow($data)->execute();
            $newId = (int)$query->lastInsertId();
            if ($newId <= 0) {
                throw new \RuntimeException('Unable to determine new user ID.');
            }
        } catch (Throwable $e) {
            $this->respondFailure('Uživatele se nepodařilo uložit.', 'admin.php?r=users&a=edit', $e);
        }

        if ($this->isAjax()) {
            $payload = [
                'success'  => true,
                'message'  => 'Uživatel vytvořen.',
                'user'     => $this->findUserRow((int)$newId),
                'redirect' => 'admin.php?r=users',
            ];
            $this->jsonResponse($payload);
        }

        $this->redirect('admin.php?r=users', 'success', 'Uživatel vytvořen.');
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
            $this->respondFailure('Vyberte uživatele k odstranění.', $redirect, status: 422, flashType: 'warning');
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
            $this->respondFailure('Žádní vybraní uživatelé pro smazání.', $redirect, status: 422, flashType: 'warning');
        }

        try {
            DB::query()->table('users')
                ->delete()
                ->whereIn('id', $targetIds)
                ->execute();
        } catch (\Throwable $e) {
            $this->respondFailure($e->getMessage(), $redirect, $e);
        }

        if ($this->isAjax()) {
            $listing = $this->prepareListing($q, $page);
            $payload = $this->listingJsonPayload($listing, 'Uživatelé byli odstraněni. (' . count($targetIds) . ')');
            $payload['removedIds'] = $targetIds;
            $this->jsonResponse($payload);
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

        $errors = [];
        if ($userId <= 0) {
            $errors['form'][] = 'Vyberte uživatele a šablonu.';
        }
        if ($templateKey === '') {
            $errors['template'][] = 'Vyberte šablonu.';
        }
        if ($errors !== []) {
            $redirect = $userId > 0 ? 'admin.php?r=users&a=edit&id=' . $userId : 'admin.php?r=users';
            $this->respondValidationErrors($errors, 'Vyberte uživatele a šablonu.', $redirect);
        }

        $user = DB::query()->table('users')->select(['*'])->where('id','=', $userId)->first();
        if (!$user) {
            $this->respondFailure('Uživatel nenalezen.', 'admin.php?r=users', status: 404);
        }

        $email = (string)($user['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->respondValidationErrors([
                'form' => ['Uživatel nemá platný e-mail.'],
            ], 'Uživatel nemá platný e-mail.', 'admin.php?r=users&a=edit&id=' . $userId);
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
            $links = new LinkGenerator(null, $settings);
            try {
                $token = bin2hex(random_bytes(20));
            } catch (\Throwable $e) {
                $this->respondFailure('Nepodařilo se vygenerovat resetovací odkaz.', 'admin.php?r=users&a=edit&id=' . $userId, $e);
            }

            $expiresAt = DateTimeFactory::now()->modify('+1 hour')->format('Y-m-d H:i:s');

            try {
                DB::query()->table('users')->update([
                    'token'        => $token,
                    'token_expire' => $expiresAt,
                    'updated_at'   => DateTimeFactory::nowString(),
                ])->where('id','=', $userId)->execute();
            } catch (\Throwable $e) {
                $this->respondFailure('Nepodařilo se uložit resetovací token.', 'admin.php?r=users&a=edit&id=' . $userId, $e);
            }

            $user['token'] = $token;
            $user['token_expire'] = $expiresAt;
            $templateData['user'] = $user;

            $templateData['resetUrl'] = $links->absolute($links->reset($token, $userId));
        }

        try {
            $template = $templateManager->render($templateKey, $templateData);
        } catch (\Throwable $e) {
            $this->respondFailure('Šablonu se nepodařilo načíst.', 'admin.php?r=users&a=edit&id=' . $userId, $e);
        }

        $mailService = new MailService($settings);
        $ok = $mailService->sendTemplate($email, $template, (string)($user['name'] ?? '') ?: null);

        if ($this->isAjax()) {
            if ($ok) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'E-mail byl odeslán.',
                ]);
            }

            $this->jsonResponse([
                'success' => false,
                'message' => 'E-mail se nepodařilo odeslat.',
            ], 500);
        }

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
        $links = new LinkGenerator(null, $settings);
        return $links->absolute($links->login());
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

    private function wantsJsonIndex(): bool
    {
        if ($this->isAjax()) {
            return true;
        }

        $format = isset($_GET['format']) ? strtolower((string)$_GET['format']) : '';
        return $format === 'json';
    }

    /**
     * @return array{
     *     items:array<int,array<string,mixed>>,
     *     pagination:array{page:int,per_page:int,total:int,pages:int},
     *     searchQuery:string,
     *     buildUrl:callable,
     *     currentUrl:string
     * }
     */
    private function prepareListing(string $q, int $page): array
    {
        $builder = DB::query()->table('users','u')->select(['u.id','u.name','u.email','u.slug','u.role','u.active','u.created_at']);
        if ($q !== '') {
            $like = "%{$q}%";
            $builder->where(function($w) use($like){
                $w->whereLike('u.name', $like)
                  ->orWhere('u.email','LIKE', $like);
            });
        }
        $builder->orderBy('u.created_at','DESC');
        $paginated = $builder->paginate($page, 20);

        $pagination = $this->paginationData($paginated, $page, 20);
        $resolvedPage = (int)($paginated['page'] ?? $pagination['page']);

        $buildUrl = $this->listingUrlBuilder([
            'r' => 'users',
            'q' => $q,
            'page' => $resolvedPage,
        ]);

        $items = $this->attachProfileLinks($this->normalizeCreatedAt($paginated['items'] ?? []));

        return [
            'items'       => $items,
            'pagination'  => $pagination,
            'searchQuery' => $q,
            'buildUrl'    => $buildUrl,
            'currentUrl'  => $this->listUrl($q, (int)($pagination['page'] ?? 1)),
        ];
    }

    /**
     * @param array{
     *     items:array<int,array<string,mixed>>,
     *     pagination:array{page:int,per_page:int,total:int,pages:int},
     *     searchQuery:string,
     *     buildUrl:callable,
     *     currentUrl:string
     * } $listing
     */
    private function listingJsonPayload(array $listing, ?string $message = null): array
    {
        $partialData = [
            'items'          => $listing['items'],
            'pagination'     => $listing['pagination'],
            'searchQuery'    => $listing['searchQuery'],
            'buildUrl'       => $listing['buildUrl'],
            'csrf'           => $this->token(),
            'currentUserId'  => (int)($this->auth->user()['id'] ?? 0),
        ];

        $payload = [
            'success'      => true,
            'pagination'   => $listing['pagination'],
            'searchQuery'  => $listing['searchQuery'],
            'csrf'         => $this->token(),
            'partials'     => [
                '[data-users-table-body]' => $this->renderPartial('users/partials/table-body', $partialData),
                '[data-users-modals]'     => $this->renderPartial('users/partials/modals', $partialData),
                '[data-users-pagination]' => $this->renderPartial('users/partials/pagination', $partialData),
            ],
            'listing'      => [
                'url'         => $listing['currentUrl'],
                'page'        => (int)($listing['pagination']['page'] ?? 1),
                'searchQuery' => $listing['searchQuery'],
            ],
        ];

        if ($message !== null && $message !== '') {
            $payload['message'] = $message;
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $errors
     */
    private function respondValidationErrors(array $errors, string $fallbackMessage, string $redirectUrl): never
    {
        $message = $this->firstValidationMessage($errors) ?? $fallbackMessage;

        if ($this->isAjax()) {
            $this->jsonResponse([
                'success' => false,
                'message' => $message,
                'errors'  => $errors,
            ], 422);
        }

        $this->redirect($redirectUrl, 'danger', $message);
    }

    private function respondFailure(string $message, string $redirectUrl, ?Throwable $exception = null, int $status = 400, string $flashType = 'danger'): never
    {
        if ($this->isAjax()) {
            $payload = [
                'success' => false,
                'message' => $message,
            ];

            if ($status === 422) {
                $payload['errors'] = ['form' => [$message]];
            }

            if ($flashType !== 'danger') {
                $payload['flash'] = [
                    'type' => $flashType,
                    'msg'  => $message,
                ];
            }

            if ($exception instanceof Throwable) {
                $payload['error'] = $exception->getMessage();
            }

            $this->jsonResponse($payload, $status);
        }

        $this->redirect($redirectUrl, $flashType, $message);
    }

    /**
     * @param array<string,mixed> $errors
     */
    private function firstValidationMessage(array $errors): ?string
    {
        foreach ($errors as $fieldErrors) {
            if (is_array($fieldErrors)) {
                foreach ($fieldErrors as $message) {
                    $text = (string)$message;
                    if ($text !== '') {
                        return $text;
                    }
                }
            } elseif (is_string($fieldErrors) && $fieldErrors !== '') {
                return $fieldErrors;
            }
        }

        return null;
    }

    private function normalizeWebsiteUrl(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (!preg_match('~^https?://~i', $trimmed)) {
            $trimmed = 'https://' . $trimmed;
        }

        $normalized = filter_var($trimmed, FILTER_VALIDATE_URL);
        if ($normalized === false) {
            return null;
        }

        return $normalized;
    }

    private function sanitizeBio(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $cleaned = preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F]+~u', '', $trimmed);
        if (!is_string($cleaned)) {
            $cleaned = $trimmed;
        }

        return str_replace(["\r\n", "\r"], "\n", $cleaned);
    }

    private function renderPartial(string $template, array $data): string
    {
        ob_start();
        try {
            $this->view->render($template, $data);
        } finally {
            $output = ob_get_clean();
        }

        return $output === false ? '' : $output;
    }

    private function findUserRow(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = DB::query()->table('users','u')
            ->select(['u.id','u.name','u.email','u.slug','u.role','u.active','u.created_at'])
            ->where('u.id','=', $id)
            ->first();

        if (!$row) {
            return null;
        }

        $normalized = $this->attachProfileLinks($this->normalizeCreatedAt([$row]));
        return $normalized[0] ?? null;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function attachProfileLinks(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $links = new LinkGenerator();

        foreach ($rows as &$row) {
            if (!is_array($row)) {
                continue;
            }

            $slug = isset($row['slug']) ? trim((string)$row['slug']) : '';
            $id = isset($row['id']) ? (int)$row['id'] : 0;
            $row['profile_url'] = $links->user($slug !== '' ? $slug : null, $id > 0 ? $id : null);
        }
        unset($row);

        return $rows;
    }
}
