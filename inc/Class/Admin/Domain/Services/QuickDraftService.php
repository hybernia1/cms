<?php
declare(strict_types=1);

namespace Cms\Admin\Domain\Services;

use Cms\Admin\Auth\AuthService;
use Cms\Admin\Domain\Repositories\PostsRepository;
use Cms\Admin\Http\Support\ControllerHelpers;
use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Utils\DateTimeFactory;
use Throwable;
use Core\Database\SchemaChecker;

final class QuickDraftService
{
    private AuthService $auth;

    private PostsService $postsService;

    private PostsRepository $postsRepository;

    private CmsSettings $settings;

    private string $redirectUrl;

    private SchemaChecker $schemaChecker;

    public function __construct(
        ?AuthService $auth = null,
        ?PostsService $postsService = null,
        ?PostsRepository $postsRepository = null,
        ?CmsSettings $settings = null,
        ?SchemaChecker $schemaChecker = null,
        string $redirectUrl = 'admin.php?r=dashboard'
    ) {
        $this->auth = $auth ?? new AuthService();
        $this->postsService = $postsService ?? new PostsService();
        $this->postsRepository = $postsRepository ?? new PostsRepository();
        $this->settings = $settings ?? new CmsSettings();
        $this->schemaChecker = $schemaChecker ?? new SchemaChecker();
        $this->redirectUrl = $redirectUrl;
    }

    public function handleQuickDraft(): void
    {
        ControllerHelpers::assertCsrf();

        if (!$this->postsTableAvailable()) {
            $message = 'Rychlé koncepty nejsou dostupné, protože modul příspěvků není aktivní.';

            if (ControllerHelpers::isAjax()) {
                ControllerHelpers::jsonResponse([
                    'success' => false,
                    'flash'   => [
                        'type' => 'warning',
                        'msg'  => $message,
                    ],
                ], 503);
                return;
            }

            $this->redirect($this->redirectUrl, 'warning', $message);
        }

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

            $this->redirect($this->redirectUrl, 'danger', $message);
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

            $this->redirect($this->redirectUrl, 'warning', 'Zadejte prosím titulek konceptu.');
        }

        try {
            $postId = $this->postsService->create([
                'title'      => $title,
                'content'    => $content,
                'type'       => $type,
                'status'     => 'draft',
                'author_id'  => $authorId,
            ]);
        } catch (Throwable $e) {
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

            $this->redirect($this->redirectUrl, 'danger', $message);
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

        $this->redirect($this->redirectUrl, 'success', 'Koncept byl vytvořen.');
    }

    /**
     * @return array<string,string>
     */
    public function pullQuickDraftOld(): array
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
     * @return array{id:int,title:string,type:string,created_at:?string,created_at_display:string,url:string}
     */
    private function buildQuickDraftPayload(int $postId, string $fallbackType): array
    {
        if (!$this->postsTableAvailable()) {
            return [
                'id'                  => $postId,
                'title'               => '',
                'type'                => $fallbackType,
                'created_at'          => null,
                'created_at_display'  => '',
                'url'                 => $this->redirectUrl,
            ];
        }

        $row = $this->postsRepository->find($postId);
        if (!is_array($row)) {
            $row = [];
        }

        $title = trim((string)($row['title'] ?? ''));
        $type = (string)($row['type'] ?? $fallbackType);
        $createdRaw = isset($row['created_at']) ? (string)$row['created_at'] : null;

        $createdAtDisplay = '';
        if ($createdRaw) {
            $createdAt = DateTimeFactory::fromStorage($createdRaw);
            if ($createdAt) {
                $createdAtDisplay = $this->settings->formatDateTime($createdAt);
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

    private function postsTableAvailable(): bool
    {
        return $this->schemaChecker->hasTable('posts');
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

    private function flashIndicatesSuccess(?array $flash): bool
    {
        if ($flash === null) {
            return true;
        }

        $type = strtolower((string)($flash['type'] ?? ''));

        return $type !== 'danger';
    }
}
