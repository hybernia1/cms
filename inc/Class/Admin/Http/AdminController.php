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
use Cms\Admin\Http\Controllers\PostsController;
use Cms\Admin\Http\Controllers\SettingsController;
use Cms\Admin\Http\Controllers\TermsController;
use Cms\Admin\Http\Controllers\ThemesController;
use Cms\Admin\Http\Controllers\UsersController;
use Cms\Admin\Http\Support\ControllerHelpers;
use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Utils\AdminNavigation;
use Cms\Admin\Utils\DateTimeFactory;
use Cms\Admin\View\ViewEngine;
use Core\Database\SchemaChecker;

final class AdminController
{
    private ViewEngine $view;
    private string $baseViewsPath;
    private AuthService $auth;
    private SchemaChecker $schemaChecker;

    /** @var array<string,class-string> */
    private const ROUTE_MAP = [
        'posts'      => PostsController::class,
        'media'      => MediaController::class,
        'terms'      => TermsController::class,
        'comments'   => CommentsController::class,
        'themes'     => ThemesController::class,
        'navigation' => NavigationController::class,
        'settings'   => SettingsController::class,
        'migrations' => MigrationsController::class,
        'users'      => UsersController::class,
    ];

    public function __construct(string $baseViewsPath, ?SchemaChecker $schemaChecker = null)
    {
        $this->baseViewsPath = $baseViewsPath;
        $this->view = new ViewEngine($baseViewsPath);
        $this->view->setBasePaths($this->resolveViewPaths($baseViewsPath));
        $this->auth = new AuthService();
        $this->schemaChecker = $schemaChecker ?? new SchemaChecker();
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
            if ($class === NavigationController::class) {
                $controller = new $class($this->baseViewsPath, $this->schemaChecker);
            } else {
                $controller = new $class($this->baseViewsPath);
            }
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
            'csrf'        => ControllerHelpers::csrfToken(),
            'quickDraftTypes' => $this->quickDraftTypes(),
            'quickDraftOld'   => $this->pullQuickDraftOld(),
            'quickDraftRecent' => $this->recentQuickDrafts(),
        ];
        $this->view->render('dashboard/index', $data);
    }

    private function dashboardQuickDraft(): void
    {
        ControllerHelpers::assertCsrf();

        $user = $this->auth->user();
        $authorId = isset($user['id']) ? (int)$user['id'] : 0;
        if ($authorId <= 0) {
            $message = 'Nelze ověřit autora konceptu.';

            if (ControllerHelpers::isAjax()) {
                ControllerHelpers::jsonResponse([
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

            if (ControllerHelpers::isAjax()) {
                ControllerHelpers::jsonResponse([
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

            if (ControllerHelpers::isAjax()) {
                ControllerHelpers::jsonResponse([
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

        if (ControllerHelpers::isAjax()) {
            ControllerHelpers::jsonResponse([
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

        if (ControllerHelpers::isAjax()) {
            $success = $this->flashIndicatesSuccess($flashPayload);
            ControllerHelpers::jsonRedirect($url, success: $success, flash: $flashPayload);
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
