<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Controllers;

use Cms\Admin\Domain\Services\PostsCrudService;
use Cms\Admin\Domain\Services\MediaService;
use Cms\Admin\Utils\AdminNavigation;
use Cms\Admin\Utils\LinkGenerator;

final class PostsController extends BaseAdminController
{
    private ?PostsCrudService $postsCrud = null;

    private function postsCrud(): PostsCrudService
    {
        if ($this->postsCrud === null) {
            $this->postsCrud = new PostsCrudService();
        }

        return $this->postsCrud;
    }

    public function handle(string $action): void
    {
        switch ($action) {
            case 'index':
            default:
                $this->index();
                return;

            case 'create':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') $this->store(); else $this->form();
                return;

            case 'edit':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') $this->update(); else $this->form();
                return;

            case 'bulk':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->bulk(); return; }
                $this->index();
                return;

            case 'autosave':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->autosave(); return; }
                $this->index();
                return;

            case 'delete':
                $this->delete();
                return;

            case 'toggle':
                $this->toggleStatus();
                return;
        }
    }

    // ---------------- Helpers ----------------
    private function typeConfig(): array
    {
        return [
            'post' => [
                'nav'    => 'Příspěvky',
                'list'   => 'Příspěvky',
                'create' => 'Nový příspěvek',
                'edit'   => 'Upravit příspěvek',
                'label'  => 'Příspěvek',
            ],
            'page' => [
                'nav'    => 'Stránky',
                'list'   => 'Stránky',
                'create' => 'Nová stránka',
                'edit'   => 'Upravit stránku',
                'label'  => 'Stránka',
            ],
        ];
    }

    private function requestedType(): string
    {
        $types = $this->typeConfig();
        $type = (string)($_GET['type'] ?? 'post');
        if (!array_key_exists($type, $types)) {
            $type = 'post';
        }
        return $type;
    }

    // ---------------- Actions ----------------

    private function index(): void
    {
        $type = $this->requestedType();
        $context = $this->contextFromQuery();

        try {
            $viewModel = $this->listingViewModel($type, $context);
        } catch (\Throwable $exception) {
            $this->redirect($this->listUrl($type, $context), 'danger', $exception->getMessage());
        }

        $this->renderAdmin('posts/index', [
            'pageTitle'    => $this->typeConfig()[$type]['list'],
            'nav'          => AdminNavigation::build('posts:' . $type),
            'filters'      => $viewModel['filters'],
            'items'        => $viewModel['items'],
            'pagination'   => $viewModel['pagination'],
            'type'         => $type,
            'types'        => $this->typeConfig(),
            'urls'         => new LinkGenerator(),
            'statusCounts' => $viewModel['statusCounts'],
            'buildUrl'     => $viewModel['buildUrl'],
            'toolbar'      => $viewModel['toolbar'],
            'bulkForm'     => $viewModel['bulkForm'],
            'context'      => $viewModel['context'],
        ]);
    }

    private function bulk(): void
    {
        $this->assertCsrf();

        $type = $this->requestedType();
        $context = $this->contextFromPayload($_POST['context'] ?? null);
        $action = (string)($_POST['bulk_action'] ?? '');
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));

        if ($ids === [] || $action === '') {
            $message = 'Vyberte položky a požadovanou akci.';
            if ($this->isAjax()) {
                $this->jsonError($message, 422);
            }
            $this->redirect($this->listUrl($type, $context), 'warning', $message);
        }

        $result = $this->postsCrud()->bulk($type, $action, $ids);
        if ($result->isFailure()) {
            $errors = $result->errors();
            $message = implode(' ', $errors);
            if ($this->isAjax()) {
                $this->jsonError($message !== '' ? $message : 'Akci se nepodařilo dokončit.', 500, $errors);
            }
            $this->redirect($this->listUrl($type, $context), 'danger', $message);
        }

        $data = $result->data();
        $count = (int)($data['count'] ?? 0);
        $message = $result->message() ?? (string)($data['message'] ?? 'Akce dokončena.');

        if ($count > 0) {
            $message .= ' (' . $count . ')';
        }

        if ($this->isAjax()) {
            $this->respondListingSuccess($type, $context, $message);
        }

        $this->redirect($this->listUrl($type, $context), 'success', $message);
    }

    private function form(): void
    {
        $id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $row = null;
        $type = $this->requestedType();
        if ($id > 0) {
            $result = $this->postsCrud()->find($id);
            if ($result->isFailure()) {
                $this->redirect($this->listUrl($type), 'danger', implode(' ', $result->errors()));
            }
            $row = $result->data();
            $rowType = (string)($row['type'] ?? 'post');
            if (array_key_exists($rowType, $this->typeConfig())) {
                $type = $rowType;
            }
        }

        $termsResult = $this->postsCrud()->termsData($id ?: null, $type);
        if ($termsResult->isFailure()) {
            $this->redirect($this->listUrl($type), 'danger', implode(' ', $termsResult->errors()));
        }
        $termsData = $termsResult->data();

        $mediaResult = $this->postsCrud()->attachedMediaIds($id);
        $attachedMedia = $mediaResult->isSuccess() ? $mediaResult->data() : [];

        $this->renderAdmin('posts/edit', [
            'pageTitle'      => $this->typeConfig()[$type][$id ? 'edit' : 'create'],
            'nav'            => AdminNavigation::build('posts:' . $type),
            'post'           => $row,
            'terms'          => $termsData['terms'] ?? [],
            'selected'       => $termsData['selected'] ?? [],
            'type'           => $type,
            'types'          => $this->typeConfig(),
            'attachedMedia'  => $attachedMedia,
        ]);
    }

    private function listUrl(string $type, ?array $context = null): string
    {
        $query = ['r' => 'posts', 'type' => $type];

        if (is_array($context)) {
            $status = (string)($context['status'] ?? '');
            $author = (string)($context['author'] ?? '');
            $search = (string)($context['q'] ?? '');
            $page = max(1, (int)($context['page'] ?? 1));

            if ($status !== '') {
                $query['status'] = $status;
            }
            if ($author !== '') {
                $query['author'] = $author;
            }
            if ($search !== '') {
                $query['q'] = $search;
            }
            if ($page > 1) {
                $query['page'] = $page;
            }
        }

        return 'admin.php?' . http_build_query($query);
    }

    /**
     * @return array{status:string,author:string,q:string,page:int}
     */
    private function contextFromQuery(): array
    {
        return [
            'status' => (string)($_GET['status'] ?? ''),
            'author' => (string)($_GET['author'] ?? ''),
            'q'      => (string)($_GET['q'] ?? ''),
            'page'   => max(1, (int)($_GET['page'] ?? 1)),
        ];
    }

    /**
     * @param mixed $payload
     * @return array{status:string,author:string,q:string,page:int}
     */
    private function contextFromPayload(mixed $payload): array
    {
        $query = $this->contextFromQuery();
        $source = is_array($payload) ? $payload : [];

        return [
            'status' => (string)($source['status'] ?? $query['status']),
            'author' => (string)($source['author'] ?? $query['author']),
            'q'      => (string)($source['q'] ?? $query['q']),
            'page'   => max(1, (int)($source['page'] ?? $query['page'])),
        ];
    }

    /**
     * @param array{status:string,author:string,q:string,page:int} $context
     * @return array<string,string>
     */
    private function contextHiddenFields(array $context): array
    {
        return [
            'context[status]' => (string)($context['status'] ?? ''),
            'context[author]' => (string)($context['author'] ?? ''),
            'context[q]'      => (string)($context['q'] ?? ''),
            'context[page]'   => (string)max(1, (int)($context['page'] ?? 1)),
        ];
    }

    /**
     * @param array<string,string> $filters
     * @param array<string,int> $statusCounts
     */
    private function buildToolbarData(string $type, array $filters, array $statusCounts, callable $buildUrl): array
    {
        $statusTabs = [
            ''        => 'Vše',
            'publish' => 'Publikované',
            'draft'   => 'Koncepty',
        ];

        $totalCount = (int)($statusCounts['__total'] ?? 0);
        if ($totalCount === 0 && $statusCounts !== []) {
            $totalCount = array_sum(array_map(static fn($value) => is_int($value) ? $value : 0, $statusCounts));
        }

        $tabs = [];
        foreach ($statusTabs as $value => $label) {
            $count = $value === '' ? $totalCount : (int)($statusCounts[$value] ?? 0);
            $tabs[] = [
                'label'  => $label,
                'href'   => $buildUrl(['status' => $value]),
                'active' => (string)$filters['status'] === $value,
                'count'  => $count,
            ];
        }

        $typeConfig = $this->typeConfig();
        $buttonLabel = (string)($typeConfig[$type]['create'] ?? 'Nový záznam');

        return [
            'tabs'      => $tabs,
            'tabsClass' => 'order-2 order-md-1',
            'search'    => [
                'action'        => 'admin.php',
                'wrapperClass'  => 'order-1 order-md-2 ms-md-auto',
                'hidden'        => ['r' => 'posts', 'type' => $type, 'status' => (string)$filters['status']],
                'value'         => (string)$filters['q'],
                'placeholder'   => 'Hledat…',
                'resetHref'     => $buildUrl(['q' => '']),
                'resetDisabled' => (string)$filters['q'] === '',
                'searchTooltip' => 'Hledat',
                'clearTooltip'  => 'Zrušit filtr',
            ],
            'button'    => [
                'href'  => 'admin.php?' . http_build_query(['r' => 'posts', 'a' => 'create', 'type' => $type]),
                'label' => $buttonLabel,
                'icon'  => 'bi bi-plus-lg',
                'class' => 'btn btn-success btn-sm order-3',
            ],
        ];
    }

    /**
     * @param array{status:string,author:string,q:string,page:int} $context
     */
    private function buildBulkFormData(string $type, array $context): array
    {
        return [
            'formId'       => 'posts-bulk-form',
            'action'       => 'admin.php?' . http_build_query(['r' => 'posts', 'a' => 'bulk', 'type' => $type]),
            'csrf'         => $this->token(),
            'selectAll'    => '#select-all',
            'rowSelector'  => '.row-check',
            'actionSelect' => '#bulk-action-select',
            'applyButton'  => '#bulk-apply',
            'counter'      => '#bulk-selection-counter',
            'hidden'       => $this->contextHiddenFields($context),
        ];
    }

    /**
     * @param array{status:string,author:string,q:string,page:int} $context
     * @return array{
     *     filters:array<string,string>,
     *     items:array<int,array<string,mixed>>,
     *     pagination:array{page:int,per_page:int,total:int,pages:int},
     *     statusCounts:array<string,int>,
     *     buildUrl:callable,
     *     toolbar:array<string,mixed>,
     *     bulkForm:array<string,mixed>,
     *     context:array{status:string,author:string,q:string,page:int}
     * }
     */
    private function listingViewModel(string $type, array $context): array
    {
        $filters = [
            'type'   => $type,
            'status' => (string)($context['status'] ?? ''),
            'author' => (string)($context['author'] ?? ''),
            'q'      => (string)($context['q'] ?? ''),
        ];

        $page = max(1, (int)($context['page'] ?? 1));
        $perPage = 15;

        $result = $this->postsCrud()->paginate($filters, $page, $perPage);
        if ($result->isFailure()) {
            throw new \RuntimeException(implode(' ', $result->errors()));
        }

        $data = $result->data();
        $pagination = $this->paginationData($data['pagination'] ?? [], $page, $perPage);
        $pages = max(1, (int)($data['pagination']['pages'] ?? $pagination['pages']));

        if ($page > $pages && $pages >= 1) {
            $page = $pages;
            $context['page'] = $page;
            $result = $this->postsCrud()->paginate($filters, $page, $perPage);
            if ($result->isFailure()) {
                throw new \RuntimeException(implode(' ', $result->errors()));
            }
            $data = $result->data();
            $pagination = $this->paginationData($data['pagination'] ?? [], $page, $perPage);
        } else {
            $context['page'] = $pagination['page'];
        }

        $items = $this->normalizeCreatedAt($data['items'] ?? [], true);
        $statusCounts = is_array($data['status_counts'] ?? null) ? $data['status_counts'] : [];

        $buildUrl = $this->listingUrlBuilder([
            'r'      => 'posts',
            'type'   => $type,
            'status' => $filters['status'],
            'author' => $filters['author'],
            'q'      => $filters['q'],
        ]);

        return [
            'filters'      => $filters,
            'items'        => $items,
            'pagination'   => $pagination,
            'statusCounts' => $statusCounts,
            'buildUrl'     => $buildUrl,
            'toolbar'      => $this->buildToolbarData($type, $filters, $statusCounts, $buildUrl),
            'bulkForm'     => $this->buildBulkFormData($type, $context),
            'context'      => $context,
        ];
    }

    /**
     * @param array{status:string,author:string,q:string,page:int} $context
     * @return never
     */
    private function respondListingSuccess(string $type, array $context, string $message, string $flashType = 'success'): never
    {
        try {
            $viewModel = $this->listingViewModel($type, $context);
        } catch (\Throwable $exception) {
            $this->jsonError($exception->getMessage(), 500);
        }

        $fragments = [
            [
                'selector' => '[data-admin-fragment="posts-toolbar"]',
                'mode'     => 'replace',
                'html'     => $this->view->renderToString('posts/parts/toolbar', [
                    'toolbar' => $viewModel['toolbar'],
                ]),
            ],
            [
                'selector' => '[data-admin-fragment="posts-bulk-form"]',
                'mode'     => 'replace',
                'html'     => $this->view->renderToString('posts/parts/bulk-form', [
                    'bulkForm' => $viewModel['bulkForm'],
                ]),
            ],
            [
                'selector' => '[data-admin-fragment="posts-table-body"]',
                'mode'     => 'replaceChildren',
                'html'     => $this->view->renderToString('posts/parts/table-rows', [
                    'items'   => $viewModel['items'],
                    'type'    => $type,
                    'csrf'    => $this->token(),
                    'urls'    => new LinkGenerator(),
                    'context' => $viewModel['context'],
                ]),
            ],
            [
                'selector' => '[data-admin-fragment="posts-pagination"]',
                'mode'     => 'replace',
                'html'     => $this->view->renderToString('posts/parts/pagination', [
                    'pagination' => $viewModel['pagination'] + ['ariaLabel' => 'Stránkování příspěvků'],
                    'buildUrl'   => $viewModel['buildUrl'],
                ]),
            ],
        ];

        $this->jsonSuccess($message, [
            'fragments' => $fragments,
            'context'   => $viewModel['context'],
        ], $flashType);
    }

    private function store(): void
    {
        $this->assertCsrf();
        try {
            $user = $this->auth->user();
            if (!$user) throw new \RuntimeException('Nejste přihlášeni.');

            $title   = trim((string)($_POST['title'] ?? ''));
            $type    = $this->requestedType();
            $status  = (string)($_POST['status'] ?? 'draft');
            $content = (string)($_POST['content'] ?? '');
            $commentsAllowed = isset($_POST['comments_allowed']) ? 1 : 0;
            if ($type !== 'post') {
                $commentsAllowed = 0;
            }

            // terms z formuláře
        $catIds = [];
        $tagIds = [];
        $newCatNames = [];
        $newTagNames = [];
        if ($type === 'post') {
            $catIds = isset($_POST['categories']) ? (array)$_POST['categories'] : [];
            $tagIds = isset($_POST['tags']) ? (array)$_POST['tags'] : [];

            $newCatNames = $this->parseNewTerms((string)($_POST['new_categories'] ?? ''));
                $newTagNames = $this->parseNewTerms((string)($_POST['new_tags'] ?? ''));
            }

            $selectedThumbId = isset($_POST['selected_thumbnail_id']) ? (int)$_POST['selected_thumbnail_id'] : 0;
            $removeThumb = isset($_POST['remove_thumbnail']) && (int)$_POST['remove_thumbnail'] === 1;
            $thumbId = null;
            if (!$removeThumb) {
                if (!empty($_FILES['thumbnail']) && (int)$_FILES['thumbnail']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $mediaSvc = new MediaService();
                    $up = $mediaSvc->uploadAndCreate($_FILES['thumbnail'], (int)$user['id'], $this->uploadPaths(), 'posts');
                    $thumbId = (int)$up['id'];
                } elseif ($selectedThumbId > 0) {
                    $thumbId = $selectedThumbId;
                }
            }

            $attachedMedia = $this->parseAttachedMedia((string)($_POST['attached_media'] ?? ''));

            $result = $this->postsCrud()->create([
                'title'            => $title,
                'type'             => $type,
                'status'           => $status,
                'content'          => $content,
                'author_id'        => (int)$user['id'],
                'thumbnail_id'     => $thumbId,
                'comments_allowed' => $commentsAllowed,
                'categories'       => $catIds,
                'tags'             => $tagIds,
                'new_categories'   => $type === 'post' ? ($newCatNames ?? []) : [],
                'new_tags'         => $type === 'post' ? ($newTagNames ?? []) : [],
                'attached_media'   => $attachedMedia,
            ]);

            if ($result->isFailure()) {
                $this->redirect(
                    'admin.php?r=posts&a=create&type=' . $type,
                    'danger',
                    implode(' ', $result->errors())
                );
            }

            $data = $result->data();
            $postId = (int)($data['id'] ?? 0);

            $this->redirect(
                'admin.php?r=posts&a=edit&id=' . (int)$postId . '&type=' . $type,
                'success',
                'Příspěvek byl vytvořen.'
            );

        } catch (\Throwable $e) {
            $type = $this->requestedType();
            $this->redirect(
                'admin.php?r=posts&a=create&type=' . $type,
                'danger',
                $e->getMessage()
            );
        }
    }

    private function update(): void
    {
        $this->assertCsrf();
        $id = (int)($_GET['id'] ?? 0);
        $type = $this->requestedType();
        if ($id <= 0) {
            $this->redirect($this->listUrl($type), 'danger', 'Chybí ID.');
        }

        $findResult = $this->postsCrud()->find($id);
        if ($findResult->isFailure()) {
            $this->redirect($this->listUrl($type), 'danger', implode(' ', $findResult->errors()));
        }
        $post = $findResult->data();
        $rowType = (string)($post['type'] ?? '');
        if (array_key_exists($rowType, $this->typeConfig())) {
            $type = $rowType;
        }

        try {
            $user = $this->auth->user();
            if (!$user) throw new \RuntimeException('Nejste přihlášeni.');

            $payload = [
                'title'            => (string)($_POST['title'] ?? ''),
                'status'           => (string)($_POST['status'] ?? 'draft'),
                'content'          => (string)($_POST['content'] ?? ''),
                'comments_allowed' => $type === 'post' ? (isset($_POST['comments_allowed']) ? 1 : 0) : 0,
                'type'             => $type,
                'categories'       => [],
                'tags'             => [],
                'new_categories'   => [],
                'new_tags'         => [],
                'attached_media'   => $this->parseAttachedMedia((string)($_POST['attached_media'] ?? '')),
            ];

            if ($type === 'post') {
                $payload['categories'] = isset($_POST['categories']) ? (array)$_POST['categories'] : [];
                $payload['tags'] = isset($_POST['tags']) ? (array)$_POST['tags'] : [];
                $payload['new_categories'] = $this->parseNewTerms((string)($_POST['new_categories'] ?? ''));
                $payload['new_tags'] = $this->parseNewTerms((string)($_POST['new_tags'] ?? ''));
            }

            $selectedThumbId = isset($_POST['selected_thumbnail_id']) ? (int)$_POST['selected_thumbnail_id'] : 0;
            $removeThumb = isset($_POST['remove_thumbnail']) && (int)$_POST['remove_thumbnail'] === 1;

            if ($removeThumb) {
                $payload['thumbnail_id'] = null;
            }

            if (!$removeThumb && !empty($_FILES['thumbnail']) && (int)$_FILES['thumbnail']['error'] !== UPLOAD_ERR_NO_FILE) {
                $mediaSvc = new MediaService();
                $up = $mediaSvc->uploadAndCreate($_FILES['thumbnail'], (int)$user['id'], $this->uploadPaths(), 'posts');
                $payload['thumbnail_id'] = (int)$up['id'];
            } elseif (!$removeThumb && $selectedThumbId > 0) {
                $payload['thumbnail_id'] = $selectedThumbId;
            }

            if (isset($_POST['slug']) && trim((string)$_POST['slug']) !== '') {
                $payload['slug'] = (string)$_POST['slug'];
            }

            $result = $this->postsCrud()->update($id, $payload);
            if ($result->isFailure()) {
                $this->redirect(
                    'admin.php?r=posts&a=edit&id=' . $id . '&type=' . $type,
                    'danger',
                    implode(' ', $result->errors())
                );
            }

            $this->redirect(
                'admin.php?r=posts&a=edit&id=' . $id . '&type=' . $type,
                'success',
                'Změny byly uloženy.'
            );

        } catch (\Throwable $e) {
            $this->redirect(
                'admin.php?r=posts&a=edit&id=' . $id . '&type=' . $type,
                'danger',
                $e->getMessage()
            );
        }
    }

    private function autosave(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $sessionToken = $_SESSION['csrf_admin'] ?? '';
        $incomingToken = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
        if ($sessionToken === '' || !hash_equals((string)$sessionToken, (string)$incomingToken)) {
            http_response_code(419);
            echo json_encode([
                'success' => false,
                'message' => 'Neplatný CSRF token.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        try {
            $user = $this->auth->user();
            if (!$user) {
                throw new \RuntimeException('Nejste přihlášeni.');
            }
            $requestedType = $this->requestedType();
            $rawId = $_POST['id'] ?? $_POST['post_id'] ?? null;
            $id = is_scalar($rawId) ? (int)$rawId : 0;

            $payload = [
                'id'                   => $id,
                'title'                => (string)($_POST['title'] ?? ''),
                'content'              => (string)($_POST['content'] ?? ''),
                'status'               => (string)($_POST['status'] ?? 'draft'),
                'type'                 => $requestedType,
                'comments_allowed'     => $requestedType === 'post' ? (isset($_POST['comments_allowed']) ? 1 : 0) : 0,
                'selected_thumbnail_id'=> isset($_POST['selected_thumbnail_id']) ? (int)$_POST['selected_thumbnail_id'] : 0,
                'remove_thumbnail'     => isset($_POST['remove_thumbnail']) && (int)$_POST['remove_thumbnail'] === 1,
                'slug'                 => isset($_POST['slug']) ? (string)$_POST['slug'] : null,
                'categories'           => $requestedType === 'post' ? (array)($_POST['categories'] ?? []) : [],
                'tags'                 => $requestedType === 'post' ? (array)($_POST['tags'] ?? []) : [],
                'new_categories'       => $requestedType === 'post' ? $this->parseNewTerms((string)($_POST['new_categories'] ?? '')) : [],
                'new_tags'             => $requestedType === 'post' ? $this->parseNewTerms((string)($_POST['new_tags'] ?? '')) : [],
                'attached_media'       => $this->parseAttachedMedia((string)($_POST['attached_media'] ?? '')),
                'author_id'            => (int)$user['id'],
            ];

            $result = $this->postsCrud()->autosave($payload);
            if ($result->isFailure()) {
                throw new \RuntimeException(implode(' ', $result->errors()));
            }

            $data = $result->data();
            if (($data['created'] ?? true) === false) {
                echo json_encode([
                    'success' => false,
                    'message' => '',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                return;
            }

            $postId = (int)($data['post_id'] ?? $payload['id'] ?? 0);
            $type = (string)($data['type'] ?? $requestedType);
            if (!array_key_exists($type, $this->typeConfig())) {
                $type = $requestedType;
            }

            $currentStatus = (string)($data['status'] ?? $payload['status']);
            $statusLabels = ['draft' => 'Koncept', 'publish' => 'Publikováno'];
            $statusLabel = $statusLabels[$currentStatus] ?? ucfirst($currentStatus);

            $response = [
                'success'     => true,
                'message'     => 'Automaticky uloženo v ' . date('H:i:s'),
                'postId'      => $postId,
                'status'      => $currentStatus,
                'statusLabel' => $statusLabel,
                'actionUrl'   => 'admin.php?' . http_build_query(['r' => 'posts', 'a' => 'edit', 'id' => $postId, 'type' => $type]),
                'type'        => $type,
            ];

            if (!empty($data['slug'])) {
                $response['slug'] = $data['slug'];
            }

            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    private function parseNewTerms(string $input): array
    {
        $parts = preg_split('/[,\n]+/', $input);
        if ($parts === false) {
            return [];
        }

        $out = [];
        foreach ($parts as $part) {
            $name = trim($part);
            if ($name !== '') {
                $out[] = $name;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return array<int>
     */
    private function parseAttachedMedia(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $values = $decoded;
        } else {
            $values = array_map('trim', explode(',', $raw));
        }

        $ids = [];
        foreach ($values as $value) {
            $id = (int)$value;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function delete(): void
    {
        $this->assertCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $type = $this->requestedType();
        $context = $this->contextFromPayload($_POST['context'] ?? null);
        if ($id <= 0) {
            $message = 'Chybí ID.';
            if ($this->isAjax()) {
                $this->jsonError($message, 422);
            }
            $this->redirect($this->listUrl($type, $context), 'danger', $message);
        }

        $result = $this->postsCrud()->delete($id);
        if ($result->isFailure()) {
            $errors = $result->errors();
            $message = implode(' ', $errors);
            if ($this->isAjax()) {
                $this->jsonError($message !== '' ? $message : 'Smazání se nezdařilo.', 500, $errors);
            }
            $this->redirect($this->listUrl($type, $context), 'danger', $message);
        }

        $data = $result->data();
        $rowType = (string)($data['type'] ?? '');
        if (array_key_exists($rowType, $this->typeConfig())) {
            $type = $rowType;
        }

        $message = 'Příspěvek byl odstraněn.';

        if ($this->isAjax()) {
            $this->respondListingSuccess($type, $context, $message);
        }

        $this->redirect($this->listUrl($type, $context), 'success', $message);
    }

    private function toggleStatus(): void
    {
        $this->assertCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $type = $this->requestedType();
        $context = $this->contextFromPayload($_POST['context'] ?? null);
        if ($id <= 0) {
            $message = 'Chybí ID.';
            if ($this->isAjax()) {
                $this->jsonError($message, 422);
            }
            $this->redirect($this->listUrl($type, $context), 'danger', $message);
        }

        $result = $this->postsCrud()->toggleStatus($id);
        if ($result->isFailure()) {
            $errors = $result->errors();
            $message = implode(' ', $errors);
            if ($this->isAjax()) {
                $this->jsonError($message !== '' ? $message : 'Nepodařilo se změnit stav.', 500, $errors);
            }
            $this->redirect($this->listUrl($type, $context), 'danger', $message);
        }

        $data = $result->data();
        $rowType = (string)($data['type'] ?? $type);
        if (array_key_exists($rowType, $this->typeConfig())) {
            $type = $rowType;
        }
        $new = (string)($data['new_status'] ?? 'draft');

        $message = $new === 'publish' ? 'Publikováno.' : 'Přepnuto na draft.';

        if ($this->isAjax()) {
            $this->respondListingSuccess($type, $context, $message);
        }

        $this->redirect(
            $this->listUrl($type, $context),
            'success',
            $message
        );
    }
}
