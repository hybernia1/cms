<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Controllers;

use Core\Database\Init as DB;
use Cms\Admin\Utils\AdminNavigation;
use Cms\Admin\Utils\DateTimeFactory;

final class CommentsController extends BaseAdminController
{
    private const PER_PAGE = 20;

    public function handle(string $action): void
    {
        switch ($action) {
            case 'index':
            default:
                $this->index(); return;

            case 'show':
                $this->show(); return;

            case 'bulk':
                $this->bulk(); return;

            case 'approve':
            case 'draft':
            case 'spam':
                $this->setStatus($action); return;

            case 'delete':
                $this->delete(); return;

            case 'reply':
                $this->reply(); return;
        }
    }

    // --------- helpers ----------
    // --------- actions ----------
    private function index(): void
    {
        $filters = [
            'status' => (string)($_GET['status'] ?? ''),
            'q'      => (string)($_GET['q'] ?? ''),
            'post'   => (string)($_GET['post'] ?? ''),
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));

        $listing = $this->prepareListingData($filters, $page);

        if ($this->wantsJsonIndex()) {
            $this->jsonResponse($this->listingJsonPayload($listing));
        }

        $this->renderAdmin('comments/index', array_merge($listing, [
            'pageTitle' => 'Komentáře',
            'nav'       => AdminNavigation::build('comments'),
        ]));
    }

    private function bulk(): void
    {
        $this->assertCsrf();

        $action = (string)($_POST['bulk_action'] ?? '');
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));

        $redirect = $this->listUrl([
            'status' => (string)($_POST['status'] ?? ''),
            'q'      => (string)($_POST['q'] ?? ''),
            'post'   => (string)($_POST['post'] ?? ''),
            'page'   => (int)($_POST['page'] ?? 1),
        ]);

        if ($ids === [] || $action === '') {
            $this->redirect($redirect, 'warning', 'Vyberte komentáře a požadovanou akci.');
        }

        $existing = DB::query()->table('comments')
            ->select(['id'])
            ->whereIn('id', $ids)
            ->get();

        $targetIds = [];
        foreach ($existing as $row) {
            $targetIds[] = (int)($row['id'] ?? 0);
        }
        $targetIds = array_values(array_filter($targetIds, static fn (int $id): bool => $id > 0));

        if ($targetIds === []) {
            $this->redirect($redirect, 'warning', 'Žádné platné komentáře pro hromadnou akci.');
        }

        try {
            switch ($action) {
                case 'published':
                case 'draft':
                case 'spam':
                    DB::query()->table('comments')
                        ->update(['status' => $action])
                        ->whereIn('id', $targetIds)
                        ->execute();
                    $message = match ($action) {
                        'published' => 'Komentáře byly schváleny.',
                        'spam'      => 'Komentáře byly označeny jako spam.',
                        default     => 'Komentáře byly uloženy jako koncept.',
                    };
                    $count = count($targetIds);
                    $removedIds = [];
                    break;

                case 'delete':
                    $allToDelete = [];
                    foreach ($targetIds as $id) {
                        $allToDelete = array_merge($allToDelete, $this->collectThreadIds($id));
                    }
                    $allToDelete = array_values(array_unique(array_filter($allToDelete, static fn (int $id): bool => $id > 0)));

                    if ($allToDelete !== []) {
                        DB::query()->table('comments')
                            ->delete()
                            ->whereIn('id', $allToDelete)
                            ->execute();
                    }

                    $count = count($allToDelete);
                    $message = 'Komentáře byly odstraněny.';
                    $removedIds = $allToDelete;
                    break;

                default:
                    $this->respondFailure('Neznámá hromadná akce.', $redirect, null, 422, 'warning');
            }
        } catch (\Throwable $e) {
            $this->respondFailure($e->getMessage(), $redirect, $e);
        }

        $suffix = $count > 0 ? ' (' . $count . ')' : '';
        $messageText = $message . $suffix;

        if ($this->isAjax()) {
            $filtersContext = [
                'status' => (string)($_POST['status'] ?? ''),
                'q'      => (string)($_POST['q'] ?? ''),
                'post'   => (string)($_POST['post'] ?? ''),
            ];
            $pageContext = max(1, (int)($_POST['page'] ?? 1));
            $listing = $this->prepareListingData($filtersContext, $pageContext);
            $payload = $this->listingJsonPayload($listing, $messageText);
            $payload['removedIds'] = $removedIds ?? [];
            $payload['affectedIds'] = $targetIds;
            $this->jsonResponse($payload);
        }

        $this->redirect($redirect, 'success', $messageText);
    }

    private function show(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('admin.php?r=comments', 'danger', 'Chybí ID.');
        }

        $row = DB::query()->table('comments','c')
            ->select([
                'c.*',
                'p.title AS post_title','p.slug AS post_slug','p.type AS post_type'
            ])
            ->join('posts p','c.post_id','=','p.id')
            ->where('c.id','=', $id)
            ->first();

        if (!$row) {
            $this->redirect('admin.php?r=comments', 'danger', 'Komentář nenalezen.');
        }

        $threadRootId = $this->resolveThreadRootId((int)$row['id']);
        $thread = $this->threadData($threadRootId, (int)$row['id']);
        $children = $thread['children'];

        $this->renderAdmin('comments/show', [
            'pageTitle' => 'Komentář #' . $id,
            'nav'       => AdminNavigation::build('comments'),
            'comment'   => $row,
            'children'  => $children,
            'replyParentId' => $threadRootId,
        ]);
    }

    private function setStatus(string $action): void
    {
        $this->assertCsrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('admin.php?r=comments', 'danger', 'Chybí ID.');
        }

        $targets = [
            'approve' => 'published',
            'draft'   => 'draft',
            'spam'    => 'spam',
        ];
        $new = $targets[$action] ?? 'draft';

        DB::query()->table('comments')->update(['status'=>$new])->where('id','=', $id)->execute();

        $message = match ($new) {
            'published' => 'Schváleno.',
            'spam'      => 'Označeno jako spam.',
            default     => 'Uloženo jako koncept.',
        };

        if ($this->isAjax()) {
            $comment = $this->findComment($id);
            $context = $this->contextFromBackUrl((string)($_POST['_back'] ?? ''));
            $listing = $this->prepareListingData($context['filters'], $context['page']);
            $payload = $this->listingJsonPayload($listing, $message);
            if ($comment) {
                $payload['comment'] = $this->serializeComment($comment);
            }
            $payload['affectedIds'] = [$id];
            $this->jsonResponse($payload);
        }

        $this->redirect(
            (string)($_POST['_back'] ?? 'admin.php?r=comments'),
            'success',
            $message
        );
    }

    private function delete(): void
    {
        $this->assertCsrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('admin.php?r=comments', 'danger', 'Chybí ID.');
        }

        $targetIds = $this->collectThreadIds($id);
        if ($targetIds) {
            DB::query()->table('comments')->delete()->whereIn('id', $targetIds)->execute();
        }

        $message = 'Komentář odstraněn.';

        if ($this->isAjax()) {
            $context = $this->contextFromBackUrl((string)($_POST['_back'] ?? ''));
            $listing = $this->prepareListingData($context['filters'], $context['page']);
            $payload = $this->listingJsonPayload($listing, $message);
            $payload['removedIds'] = $targetIds;
            $this->jsonResponse($payload);
        }

        $this->redirect((string)($_POST['_back'] ?? 'admin.php?r=comments'), 'success', $message);
    }

    private function reply(): void
    {
        $this->assertCsrf();
        $parentId = (int)($_POST['parent_id'] ?? 0);
        $content  = trim((string)($_POST['content'] ?? ''));
        if ($parentId <= 0 || $content === '') {
            $this->redirect('admin.php?r=comments', 'danger', 'Chybí text odpovědi.');
        }

        $parent = DB::query()->table('comments')->select(['*'])->where('id','=', $parentId)->first();
        if (!$parent) {
            $this->redirect('admin.php?r=comments', 'danger', 'Původní komentář nenalezen.');
        }

        $threadRootId = $this->resolveThreadRootId((int)$parent['id']);
        if ($threadRootId <= 0) {
            $threadRootId = (int)$parent['id'];
        }

        $user = $this->auth->user();
        $authorName  = (string)($user['name'] ?? 'Admin');
        $authorEmail = (string)($user['email'] ?? '');

        $commentId = (int) DB::query()->table('comments')->insertRow([
            'post_id'      => (int)$parent['post_id'],
            'user_id'      => (int)($user['id'] ?? 0),
            'author_name'  => $authorName,
            'author_email' => $authorEmail,
            'content'      => $content,
            'status'       => 'published',
            'parent_id'    => $threadRootId,
            'ip'           => $_SERVER['REMOTE_ADDR'] ?? '',
            'ua'           => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at'   => DateTimeFactory::nowString(),
        ])->insertGetId();

        if ($this->isAjax()) {
            $thread = $this->threadData($threadRootId);
            $newComment = $this->findComment($commentId);
            $payload = [
                'success' => true,
                'message' => 'Odpověď byla přidána.',
                'comment' => $newComment ? $this->serializeComment($newComment) : null,
                'partials' => [
                    '[data-comment-thread]' => $this->renderPartial('comments/partials/thread', [
                        'children' => $thread['children'],
                    ]),
                ],
            ];
            $this->jsonResponse($payload);
        }

        $this->redirect('admin.php?r=comments&a=show&id=' . $threadRootId, 'success', 'Odpověď byla přidána.');
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
     * @param array<string,string> $filters
     * @return array<string,mixed>
     */
    private function prepareListingData(array $filters, int $page, int $perPage = self::PER_PAGE): array
    {
        $normalized = [
            'status' => trim((string)($filters['status'] ?? '')),
            'q'      => trim((string)($filters['q'] ?? '')),
            'post'   => trim((string)($filters['post'] ?? '')),
        ];

        $query = DB::query()->table('comments', 'c')
            ->select([
                'c.id','c.post_id','c.user_id','c.author_name','c.author_email',
                'c.content','c.status','c.parent_id','c.created_at',
                'p.title AS post_title','p.slug AS post_slug','p.type AS post_type'
            ])
            ->join('posts p','c.post_id','=','p.id')
            ->orderBy('c.created_at','DESC');

        if ($normalized['status'] !== '') {
            $query->where('c.status', '=', $normalized['status']);
        }
        if ($normalized['q'] !== '') {
            $like = '%' . $normalized['q'] . '%';
            $query->where(function ($w) use ($like) {
                $w->whereLike('c.content', $like)
                    ->orWhere('c.author_name', 'LIKE', $like)
                    ->orWhere('c.author_email', 'LIKE', $like);
            });
        }
        if ($normalized['post'] !== '') {
            if (ctype_digit($normalized['post'])) {
                $query->where('c.post_id', '=', (int)$normalized['post']);
            } else {
                $query->where('p.slug', '=', $normalized['post']);
            }
        }

        $paginated = $query->paginate($page, $perPage);
        $pagination = $this->paginationData($paginated, $page, $perPage);

        $buildUrl = $this->listingUrlBuilder([
            'r'      => 'comments',
            'status' => $normalized['status'] !== '' ? $normalized['status'] : null,
            'q'      => $normalized['q'] !== '' ? $normalized['q'] : null,
            'post'   => $normalized['post'] !== '' ? $normalized['post'] : null,
            'page'   => $pagination['page'],
        ]);

        $items = $this->normalizeCreatedAt($paginated['items'] ?? [], true);
        $currentUrl = $buildUrl(['page' => $pagination['page']]);

        return [
            'filters'      => $normalized,
            'items'        => $items,
            'pagination'   => $pagination,
            'statusCounts' => $this->countByStatus(),
            'buildUrl'     => $buildUrl,
            'csrf'         => $this->token(),
            'currentUrl'   => $currentUrl,
        ];
    }

    /**
     * @param array<string,mixed> $listing
     * @return array<string,mixed>
     */
    private function listingJsonPayload(array $listing, ?string $message = null): array
    {
        $partialData = [
            'filters'      => $listing['filters'],
            'items'        => $listing['items'],
            'pagination'   => $listing['pagination'],
            'statusCounts' => $listing['statusCounts'],
            'buildUrl'     => $listing['buildUrl'],
            'csrf'         => $listing['csrf'],
            'backUrl'      => $listing['currentUrl'],
        ];

        $payload = [
            'success'      => true,
            'filters'      => $listing['filters'],
            'pagination'   => $listing['pagination'],
            'statusCounts' => $listing['statusCounts'],
            'csrf'         => $listing['csrf'],
            'partials'     => [
                '[data-comments-toolbar]'    => $this->renderPartial('comments/partials/toolbar', $partialData),
                '[data-comments-filters]'    => $this->renderPartial('comments/partials/filters', $partialData),
                '[data-comments-bulk]'       => $this->renderPartial('comments/partials/bulk', $partialData),
                '[data-comments-table]'      => $this->renderPartial('comments/partials/table', $partialData),
                '[data-comments-pagination]' => $this->renderPartial('comments/partials/pagination', $partialData),
            ],
            'listing'      => [
                'url'    => $listing['currentUrl'],
                'page'   => (int)($listing['pagination']['page'] ?? 1),
                'filters'=> $listing['filters'],
            ],
        ];

        if ($message !== null && $message !== '') {
            $payload['message'] = $message;
        }

        return $payload;
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

    /**
     * @return array{filters:array<string,string>,page:int}
     */
    private function contextFromBackUrl(string $url): array
    {
        $filters = ['status' => '', 'q' => '', 'post' => ''];
        $page = 1;

        if ($url !== '') {
            $parts = parse_url($url);
            if (!empty($parts['query'])) {
                $params = [];
                parse_str((string)$parts['query'], $params);
                $filters['status'] = isset($params['status']) ? (string)$params['status'] : '';
                $filters['q'] = isset($params['q']) ? (string)$params['q'] : '';
                $filters['post'] = isset($params['post']) ? (string)$params['post'] : '';
                $page = isset($params['page']) ? max(1, (int)$params['page']) : 1;
            }
        }

        return ['filters' => $filters, 'page' => $page];
    }

    private function findComment(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = DB::query()->table('comments', 'c')
            ->select([
                'c.id','c.post_id','c.user_id','c.author_name','c.author_email',
                'c.content','c.status','c.parent_id','c.created_at',
                'p.title AS post_title','p.slug AS post_slug','p.type AS post_type'
            ])
            ->join('posts p','c.post_id','=','p.id')
            ->where('c.id','=', $id)
            ->first();

        if (!$row) {
            return null;
        }

        $normalized = $this->normalizeCreatedAt([$row], true);
        return $normalized[0] ?? null;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function serializeComment(array $row): array
    {
        return [
            'id'           => (int)($row['id'] ?? 0),
            'post_id'      => (int)($row['post_id'] ?? 0),
            'user_id'      => (int)($row['user_id'] ?? 0),
            'author_name'  => (string)($row['author_name'] ?? ''),
            'author_email' => (string)($row['author_email'] ?? ''),
            'content'      => (string)($row['content'] ?? ''),
            'status'       => (string)($row['status'] ?? ''),
            'parent_id'    => (int)($row['parent_id'] ?? 0),
            'post_title'   => (string)($row['post_title'] ?? ''),
            'post_slug'    => (string)($row['post_slug'] ?? ''),
            'post_type'    => (string)($row['post_type'] ?? ''),
            'created_at'   => [
                'raw'     => (string)($row['created_at_raw'] ?? ($row['created_at'] ?? '')),
                'display' => (string)($row['created_at_display'] ?? ($row['created_at_raw'] ?? '')),
                'iso'     => (string)($row['created_at_iso'] ?? ''),
            ],
        ];
    }

    /**
     * @return array{children:array<int,array<string,mixed>>}
     */
    private function threadData(int $threadRootId, ?int $excludeId = null): array
    {
        $children = [];
        if ($threadRootId > 0) {
            $threadIds = $this->collectThreadIds($threadRootId);
            $exclude = [$threadRootId];
            if ($excludeId !== null) {
                $exclude[] = $excludeId;
            }
            $childIds = array_values(array_diff($threadIds, $exclude));
            if ($childIds) {
                $rows = DB::query()->table('comments', 'c')
                    ->select(['c.*'])
                    ->whereIn('c.id', $childIds)
                    ->orderBy('c.created_at', 'ASC')
                    ->get();
                $children = $this->normalizeCreatedAt($rows);
            }
        }

        return ['children' => $children];
    }

    private function respondFailure(string $message, string $redirectUrl, ?\Throwable $exception = null, int $status = 400, string $flashType = 'danger'): never
    {
        if ($this->isAjax()) {
            $payload = [
                'success' => false,
                'message' => $message,
            ];

            if ($status === 422) {
                $payload['errors'] = ['form' => [$message]];
            }

            if ($exception instanceof \Throwable) {
                $payload['error'] = $exception->getMessage();
            }

            if ($flashType !== 'danger') {
                $payload['flash'] = [
                    'type' => $flashType,
                    'msg'  => $message,
                ];
            }

            $this->jsonResponse($payload, $status);
        }

        $this->redirect($redirectUrl, $flashType, $message);
    }

    private function collectThreadIds(int $rootId): array
    {
        if ($rootId <= 0) {
            return [];
        }

        $ids = [$rootId];
        $queue = [$rootId];

        while ($queue) {
            $children = DB::query()->table('comments')->select(['id'])->whereIn('parent_id', $queue)->get();
            $queue = [];
            foreach ($children as $child) {
                $childId = (int)($child['id'] ?? 0);
                if ($childId > 0 && !in_array($childId, $ids, true)) {
                    $ids[] = $childId;
                    $queue[] = $childId;
                }
            }
        }

        return $ids;
    }

    /**
     * @return array<string,int>
     */
    private function countByStatus(): array
    {
        $rows = DB::query()->table('comments')
            ->select(['status', 'COUNT(*) AS aggregate'])
            ->groupBy('status')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $status = (string)($row['status'] ?? '');
            if ($status === '') {
                continue;
            }

            $result[$status] = isset($row['aggregate']) ? (int)$row['aggregate'] : 0;
        }

        $result['__total'] = array_sum($result);

        return $result;
    }

    private function listUrl(array $params): string
    {
        $query = [
            'r'      => 'comments',
            'status' => trim((string)($params['status'] ?? '')),
            'q'      => trim((string)($params['q'] ?? '')),
            'post'   => trim((string)($params['post'] ?? '')),
        ];

        $page = (int)($params['page'] ?? 1);
        if ($page > 1) {
            $query['page'] = $page;
        }

        $query = array_filter(
            $query,
            static fn ($value): bool => $value !== '' && $value !== null,
        );

        $qs = http_build_query($query);

        return $qs === '' ? 'admin.php?r=comments' : 'admin.php?' . $qs;
    }

    private function resolveThreadRootId(int $commentId): int
    {
        if ($commentId <= 0) {
            return 0;
        }

        $currentId = $commentId;
        $guard = 0;
        while ($currentId > 0 && $guard < 20) {
            $row = DB::query()->table('comments')->select(['id','parent_id'])->where('id','=', $currentId)->first();
            if (!$row) {
                return $commentId;
            }
            $parentId = (int)($row['parent_id'] ?? 0);
            if ($parentId <= 0 || $parentId === $currentId) {
                return (int)$row['id'];
            }
            $currentId = $parentId;
            $guard++;
        }

        return $commentId;
    }
}
