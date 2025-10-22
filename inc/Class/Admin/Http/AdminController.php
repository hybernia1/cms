<?php
declare(strict_types=1);

namespace Cms\Admin\Http;

use Cms\Admin\Auth\AuthService;
use Cms\Admin\Domain\Repositories\PostsRepository;
use Cms\Admin\Domain\Services\PostsService;
use Cms\Admin\Http\Controllers\CommentsController;
use Cms\Admin\Http\Controllers\MediaController;
use Cms\Admin\Http\Controllers\MigrationsController;
use Cms\Admin\Http\Controllers\NavigationController;
use Cms\Admin\Http\Controllers\NewsletterController;
use Cms\Admin\Http\Controllers\NewsletterCampaignController;
use Cms\Admin\Http\Controllers\PostsController;
use Cms\Admin\Http\Controllers\SettingsController;
use Cms\Admin\Http\Controllers\TermsController;
use Cms\Admin\Http\Controllers\ThemesController;
use Cms\Admin\Http\Controllers\UsersController;
use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Utils\AdminNavigation;
use Cms\Admin\Utils\DateTimeFactory;
use Cms\Admin\View\ViewEngine;

final class AdminController
{
    private ViewEngine $view;
    private string $baseViewsPath;
    private AuthService $auth;

    /** @var array<string,class-string> */
    private const ROUTE_MAP = [
        'posts'      => PostsController::class,
        'media'      => MediaController::class,
        'terms'      => TermsController::class,
        'comments'   => CommentsController::class,
        'themes'     => ThemesController::class,
        'navigation' => NavigationController::class,
        'newsletter' => NewsletterController::class,
        'newsletter-campaigns' => NewsletterCampaignController::class,
        'settings'   => SettingsController::class,
        'migrations' => MigrationsController::class,
        'users'      => UsersController::class,
    ];

    public function __construct(string $baseViewsPath)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $this->baseViewsPath = $baseViewsPath;
        $this->view = new ViewEngine($baseViewsPath);
        $this->view->setBasePaths($this->resolveViewPaths($baseViewsPath));
        $this->auth = new AuthService();
    }

    /**
     * @return array<int,string>
     */
    private function resolveViewPaths(string $baseViewsPath): array
    {
        $paths = [];

        $realBase = realpath($baseViewsPath);
        if ($realBase !== false && is_dir($realBase)) {
            $paths[] = $realBase;
        }

        $adminRoot = realpath(dirname($baseViewsPath));
        if ($adminRoot !== false && is_dir($adminRoot)) {
            $paths[] = $adminRoot;
        }

        return $paths === [] ? [$baseViewsPath] : $paths;
    }

    public function handle(string $route, string $action): void
    {
        if (isset(self::ROUTE_MAP[$route])) {
            $class = self::ROUTE_MAP[$route];
            $controller = new $class($this->baseViewsPath);
            $controller->handle($action);
            return;
        }

        if ($route === 'dashboard') {
            if ($action === 'quick-draft' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->dashboardQuickDraft();
                return;
            }
            $this->dashboardIndex();
            return;
        }

        $this->dashboardIndex();
    }

    private function dashboardIndex(): void
    {
        $data = [
            'pageTitle'   => 'Dashboard',
            'nav'         => AdminNavigation::build('dashboard'),
            'currentUser' => $this->auth->user(),
            'flash'       => $this->takeFlash(),
            'csrf'        => $this->csrfToken(),
            'quickDraftTypes' => $this->quickDraftTypes(),
            'quickDraftOld'   => $this->pullQuickDraftOld(),
            'quickDraftRecent' => $this->recentQuickDrafts(),
        ];
        $this->view->render('dashboard/index', $data);
    }

    private function dashboardQuickDraft(): void
    {
        $this->assertCsrf();

        $user = $this->auth->user();
        $authorId = isset($user['id']) ? (int)$user['id'] : 0;
        if ($authorId <= 0) {
            $message = 'Nelze ověřit autora konceptu.';

            if ($this->isAjax()) {
                $this->jsonResponse([
                    'success' => false,
                    'flash'   => [
                        'type' => 'danger',
                        'msg'  => $message,
                    ],
                ], 403);
            }

            $this->redirect('admin.php?r=dashboard', 'danger', $message);
        }

        $title = trim((string)($_POST['title'] ?? ''));
        $content = trim((string)($_POST['content'] ?? ''));
        $type = 'post';

        $values = [
            'title'   => $title,
            'content' => $content,
            'type'    => $type,
        ];
        if ($title === '') {
            $this->storeQuickDraftOld($values);

            if ($this->isAjax()) {
                $this->jsonResponse([
                    'success' => false,
                    'flash'   => [
                        'type' => 'warning',
                        'msg'  => 'Zadejte prosím titulek konceptu.',
                    ],
                ], 422);
            }

            $this->redirect('admin.php?r=dashboard', 'warning', 'Zadejte prosím titulek konceptu.');
        }

        try {
            $service = new PostsService();
            $postId = $service->create([
                'title'      => $title,
                'content'    => $content,
                'type'       => $type,
                'status'     => 'draft',
                'author_id'  => $authorId,
            ]);
        } catch (\Throwable $e) {
            $this->storeQuickDraftOld($values);

            $message = 'Koncept se nepodařilo uložit: ' . $e->getMessage();

            if ($this->isAjax()) {
                $this->jsonResponse([
                    'success' => false,
                    'flash'   => [
                        'type' => 'danger',
                        'msg'  => $message,
                    ],
                ], 500);
            }

            $this->redirect('admin.php?r=dashboard', 'danger', $message);
        }

        $this->storeQuickDraftOld([]);

        $draftPayload = $this->buildQuickDraftPayload($postId, $type);

        if ($this->isAjax()) {
            $this->jsonResponse([
                'success' => true,
                'flash'   => [
                    'type' => 'success',
                    'msg'  => 'Koncept byl vytvořen.',
                ],
                'draft'   => $draftPayload,
            ]);
        }

        $this->redirect('admin.php?r=dashboard', 'success', 'Koncept byl vytvořen.');
    }

    /**
     * @return array{id:int,title:string,type:string,created_at:?string,created_at_display:string,url:string}
     */
    private function buildQuickDraftPayload(int $postId, string $fallbackType): array
    {
        $repository = new PostsRepository();
        $row = $repository->find($postId);
        if (!is_array($row)) {
            $row = [];
        }

        $title = trim((string)($row['title'] ?? ''));
        $type = (string)($row['type'] ?? $fallbackType);
        $createdRaw = isset($row['created_at']) ? (string)$row['created_at'] : null;

        $settings = new CmsSettings();
        $createdAtDisplay = '';
        if ($createdRaw) {
            $createdAt = DateTimeFactory::fromStorage($createdRaw);
            if ($createdAt) {
                $createdAtDisplay = $settings->formatDateTime($createdAt);
            }
        }

        $query = http_build_query([
            'r'    => 'posts',
            'a'    => 'edit',
            'id'   => $postId,
            'type' => $type !== '' ? $type : $fallbackType,
        ]);

        return [
            'id'                  => $postId,
            'title'               => $title,
            'type'                => $type !== '' ? $type : $fallbackType,
            'created_at'          => $createdRaw,
            'created_at_display'  => $createdAtDisplay,
            'url'                 => 'admin.php?' . $query,
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    private function jsonResponse(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function jsonSuccess(array $data = [], int $status = 200): never
    {
        $payload = ['success' => true, 'ok' => true];

        if ($data !== []) {
            $payload = array_merge($payload, $data);
        }

        $this->jsonResponse($payload, $status);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function jsonError(string|array $errors, array $data = [], int $status = 400): never
    {
        $payload = array_merge(['success' => false, 'ok' => false], $data);
        $normalized = $this->normalizeErrors($errors);

        if (count($normalized) === 1) {
            $payload['error'] = $normalized[0];
        } elseif ($normalized !== []) {
            $payload['errors'] = $normalized;
        }

        $this->jsonResponse($payload, $status);
    }

    /**
     * @param array{type?:string,msg?:string}|null $flash
     */
    private function jsonRedirect(string $url, ?array $flash = null, int $status = 200): never
    {
        $success = $this->flashIndicatesSuccess($flash);
        $payload = [
            'success'  => $success,
            'ok'       => $success,
            'redirect' => $url,
        ];

        if ($flash !== null) {
            $payload['flash'] = [
                'type' => (string)($flash['type'] ?? ''),
                'msg'  => (string)($flash['msg'] ?? ''),
            ];
        }

        $this->jsonResponse($payload, $status);
    }

    /**
     * @return list<string>
     */
    private function normalizeErrors(string|array $errors): array
    {
        if (is_string($errors)) {
            $errors = [$errors];
        }

        $normalized = [];

        foreach ($errors as $error) {
            if (is_string($error)) {
                $trimmed = trim($error);
                if ($trimmed !== '') {
                    $normalized[] = $trimmed;
                }
                continue;
            }

            if (is_array($error)) {
                foreach ($this->normalizeErrors($error) as $nested) {
                    $normalized[] = $nested;
                }
            }
        }

        return $normalized;
    }

    /**
     * @return array<int,array{value:string,label:string}>
     */
    private function quickDraftTypes(): array
    {
        return [
            ['value' => 'post', 'label' => 'Příspěvek'],
        ];
    }

    /**
     * @return array<int,array{id:int,title:string,type:string,created_at_display:string}>
     */
    private function recentQuickDrafts(): array
    {
        $service = new PostsService();
        $settings = new CmsSettings();
        $items = [];
        foreach ($service->latestDrafts('post', 5) as $row) {
            $id = isset($row['id']) ? (int)$row['id'] : 0;
            if ($id <= 0) {
                continue;
            }
            $title = trim((string)($row['title'] ?? ''));
            $createdRaw = isset($row['created_at']) ? (string)$row['created_at'] : null;
            $createdAt = $createdRaw !== null ? DateTimeFactory::fromStorage($createdRaw) : null;
            $items[] = [
                'id' => $id,
                'title' => $title,
                'type' => (string)($row['type'] ?? 'post'),
                'created_at_display' => $createdAt ? $settings->formatDateTime($createdAt) : '',
            ];
        }

        return $items;
    }

    private function isAjax(): bool
    {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }

        $accept = isset($_SERVER['HTTP_ACCEPT']) ? (string)$_SERVER['HTTP_ACCEPT'] : '';
        return str_contains($accept, 'application/json');
    }

    private function csrfToken(): string
    {
        if (empty($_SESSION['csrf_admin'])) {
            $_SESSION['csrf_admin'] = bin2hex(random_bytes(16));
        }

        return (string)$_SESSION['csrf_admin'];
    }

    private function assertCsrf(): void
    {
        $incoming = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
        if (empty($_SESSION['csrf_admin']) || !hash_equals((string)$_SESSION['csrf_admin'], (string)$incoming)) {
            if ($this->isAjax()) {
                $this->jsonError('CSRF token invalid', status: 419);
            }

            http_response_code(419);
            header('Content-Type: text/plain; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            echo 'CSRF token invalid';
            exit;
        }
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['_flash'] = ['type' => $type, 'msg' => $message];
    }

    private function redirect(string $url, ?string $flashType = null, ?string $flashMessage = null): never
    {
        if ($flashType !== null && $flashMessage !== null) {
            $this->flash($flashType, $flashMessage);
        }

        $flash = $_SESSION['_flash'] ?? null;
        $flashPayload = is_array($flash) ? $flash : null;

        if ($this->isAjax()) {
            $this->jsonRedirect($url, $flashPayload);
        }

        header('Location: ' . $url);
        exit;
    }

    private function storeQuickDraftOld(array $data): void
    {
        if ($data === []) {
            unset($_SESSION['_quick_draft_old']);
            return;
        }

        $_SESSION['_quick_draft_old'] = [
            'title'   => (string)($data['title'] ?? ''),
            'content' => (string)($data['content'] ?? ''),
            'type'    => (string)($data['type'] ?? 'post'),
        ];
    }

    /**
     * @return array<string,string>
     */
    private function pullQuickDraftOld(): array
    {
        $old = $_SESSION['_quick_draft_old'] ?? null;
        unset($_SESSION['_quick_draft_old']);

        if (!is_array($old)) {
            return ['title' => '', 'content' => '', 'type' => 'post'];
        }

        return [
            'title'   => (string)($old['title'] ?? ''),
            'content' => (string)($old['content'] ?? ''),
            'type'    => (string)($old['type'] ?? 'post'),
        ];
    }

    private function takeFlash(): ?array
    {
        $f = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);
        return is_array($f) ? $f : null;
    }

    private function flashIndicatesSuccess(?array $flash): bool
    {
        if ($flash === null) {
            return true;
        }

        $type = strtolower((string)($flash['type'] ?? ''));

        return $type !== 'danger';
    }
}
