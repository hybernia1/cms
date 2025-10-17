<?php
declare(strict_types=1);

namespace Cms\Http;

use Cms\Auth\AuthService;
use Cms\Domain\Services\PostsService;
use Cms\Http\Admin\CommentsController;
use Cms\Http\Admin\MediaController;
use Cms\Http\Admin\MigrationsController;
use Cms\Http\Admin\NavigationController;
use Cms\Http\Admin\PostsController;
use Cms\Http\Admin\SettingsController;
use Cms\Http\Admin\TermsController;
use Cms\Http\Admin\ThemesController;
use Cms\Http\Admin\UsersController;
use Cms\Utils\AdminNavigation;
use Cms\View\ViewEngine;

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
        $this->auth = new AuthService();
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
        ];
        $this->view->render('dashboard/index', $data);
    }

    private function dashboardQuickDraft(): void
    {
        $this->assertCsrf();

        $user = $this->auth->user();
        $authorId = isset($user['id']) ? (int)$user['id'] : 0;
        if ($authorId <= 0) {
            $this->redirect('admin.php?r=dashboard', 'danger', 'Nelze ověřit autora konceptu.');
        }

        $title = trim((string)($_POST['title'] ?? ''));
        $content = trim((string)($_POST['content'] ?? ''));
        $type = (string)($_POST['type'] ?? 'post');
        $allowedTypes = array_map(static fn (array $item): string => (string)$item['value'], $this->quickDraftTypes());
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'post';
        }

        $values = [
            'title'   => $title,
            'content' => $content,
            'type'    => $type,
        ];
        $this->storeQuickDraftOld($values);

        if ($title === '') {
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
            $this->redirect('admin.php?r=dashboard', 'danger', 'Koncept se nepodařilo uložit: ' . $e->getMessage());
        }

        $this->storeQuickDraftOld([]);

        $this->redirect(
            'admin.php?r=posts&a=edit&id=' . (int)$postId . '&type=' . urlencode($type),
            'success',
            'Koncept byl vytvořen.'
        );
    }

    /**
     * @return array<int,array{value:string,label:string}>
     */
    private function quickDraftTypes(): array
    {
        return [
            ['value' => 'post', 'label' => 'Příspěvek'],
            ['value' => 'page', 'label' => 'Stránka'],
        ];
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
            http_response_code(419);
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

        if ($this->isAjax()) {
            $payload = [
                'success'  => $this->flashIndicatesSuccess($flash),
                'redirect' => $url,
            ];

            if (is_array($flash)) {
                $payload['flash'] = [
                    'type' => (string)($flash['type'] ?? ''),
                    'msg'  => (string)($flash['msg'] ?? ''),
                ];
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
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

    private function flashIndicatesSuccess($flash): bool
    {
        if (!is_array($flash)) {
            return true;
        }

        $type = strtolower((string)($flash['type'] ?? ''));

        return $type !== 'danger';
    }
}
