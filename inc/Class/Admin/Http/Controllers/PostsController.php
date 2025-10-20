<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Controllers;

use Cms\Admin\Domain\Repositories\PostsRepository;
use Cms\Admin\Domain\Repositories\TermsRepository;
use Cms\Admin\Domain\Services\PostsService;
use Cms\Admin\Domain\Services\MediaService;
use Cms\Admin\Domain\Services\TermsService;
use Cms\Admin\Utils\AdminNavigation;
use Cms\Admin\Utils\DateTimeFactory;
use Cms\Admin\Utils\LinkGenerator;
use Cms\Admin\Utils\Slugger;
use Core\Database\Init as DB;

final class PostsController extends BaseAdminController
{
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

    /** Načte seznam termů rozdělený podle typu + aktuálně vybrané pro daný post. */
    private function termsData(?int $postId, string $type): array
    {
        $byType = ['category'=>[], 'tag'=>[]];
        $selected = ['category'=>[], 'tag'=>[]];

        if ($type !== 'post') {
            return ['byType'=>$byType, 'selected'=>$selected];
        }

        // všecky termy
        $all = DB::query()->table('terms','t')
            ->select(['t.id','t.name','t.slug','t.type'])
            ->orderBy('t.type','ASC')->orderBy('t.name','ASC')->get();

        foreach ($all as $t) {
            $termType = (string)$t['type'];
            $byType[$termType][] = $t;
        }

        // předvybrané
        if ($postId) {
            $rows = DB::query()->table('post_terms','pt')
                ->select(['pt.term_id','t.type'])
                ->join('terms t','pt.term_id','=','t.id')
                ->where('pt.post_id','=', $postId)
                ->get();
            foreach ($rows as $r) {
                $selected[(string)$r['type']][] = (int)$r['term_id'];
            }
        }
        return ['byType'=>$byType, 'selected'=>$selected];
    }

    /**
     * @return array<int>
     */
    private function attachedMediaIds(int $postId): array
    {
        if ($postId <= 0) {
            return [];
        }

        $rows = DB::query()->table('post_media', 'pm')
            ->select(['pm.media_id'])
            ->join('media m', 'pm.media_id', '=', 'm.id')
            ->where('pm.post_id', '=', $postId)
            ->orderBy('m.created_at', 'ASC')
            ->get();

        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int)($row['media_id'] ?? 0);
        }

        return array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
    }

    /** Uloží vazby post↔terms (přepíše existující). */
    private function syncTerms(int $postId, array $categoryIds, array $tagIds): void
    {
        // očisti na int a unikáty
        $cat = array_values(array_unique(array_map('intval', $categoryIds)));
        $tag = array_values(array_unique(array_map('intval', $tagIds)));

        DB::query()->table('post_terms')->delete()->where('post_id','=', $postId)->execute();

        $ins = DB::query()->table('post_terms')->insert(['post_id','term_id']);
        $hasRows = false;
        foreach (array_merge($cat, $tag) as $tid) {
            if ($tid > 0) {
                $ins->values([$postId, $tid]);
                $hasRows = true;
            }
        }
        if ($hasRows) {
            $ins->execute();
        }
    }

    // ---------------- Actions ----------------

    private function index(): void
    {
        $repo = new PostsRepository();

        $types = $this->typeConfig();
        $type = $this->requestedType();
        $filters = [
            'type'   => $type,
            'status' => (string)($_GET['status'] ?? ''),
            'author' => (string)($_GET['author'] ?? ''),
            'q'      => (string)($_GET['q'] ?? ''),
        ];
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 15;

        $pag = $repo->paginate($filters, $page, $perPage);
        $statusCounts = $repo->countByStatus($type);

        $pagination = $this->paginationData($pag, $page, $perPage);
        $buildUrl = $this->listingUrlBuilder([
            'r'      => 'posts',
            'type'   => $type,
            'status' => $filters['status'],
            'author' => $filters['author'],
            'q'      => $filters['q'],
        ]);

        $items = $this->normalizeCreatedAt($pag['items'] ?? [], true);
        $urls = new LinkGenerator();

        if ($this->wantsJsonIndex()) {
            $partials = [
                'toolbar' => $this->renderPartial('posts/partials/toolbar', [
                    'filters'      => $filters,
                    'type'         => $type,
                    'types'        => $types,
                    'urls'         => $urls,
                    'statusCounts' => $statusCounts,
                    'buildUrl'     => $buildUrl,
                ]),
                'table' => $this->renderPartial('posts/partials/table', [
                    'items' => $items,
                    'csrf'  => $this->token(),
                    'type'  => $type,
                    'urls'  => $urls,
                ]),
                'pagination' => $this->renderPartial('posts/partials/pagination', [
                    'pagination' => $pagination,
                    'buildUrl'   => $buildUrl,
                ]),
            ];

            $payload = [
                'success'      => true,
                'items'        => $items,
                'filters'      => $filters,
                'pagination'   => $pagination,
                'statusCounts' => $statusCounts,
                'partials'     => $partials,
            ];

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $this->renderAdmin('posts/index', [
            'pageTitle'  => $types[$type]['list'] ?? 'Příspěvky',
            'nav'        => AdminNavigation::build('posts:' . $type),
            'filters'    => $filters,
            'items'      => $items,
            'pagination' => $pagination,
            'type'       => $type,
            'types'      => $types,
            'urls'       => $urls,
            'statusCounts' => $statusCounts,
            'buildUrl'   => $buildUrl,
        ]);
    }

    private function wantsJsonIndex(): bool
    {
        $format = isset($_GET['format']) ? strtolower((string)$_GET['format']) : '';
        return $format === 'json';
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

    private function bulk(): void
    {
        $this->assertCsrf();

        $type = $this->requestedType();
        $action = (string)($_POST['bulk_action'] ?? '');
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));

        if ($ids === [] || $action === '') {
            $this->redirect($this->listUrl($type), 'warning', 'Vyberte položky a požadovanou akci.');
        }

        $existing = DB::query()->table('posts')
            ->select(['id'])
            ->where('type', '=', $type)
            ->whereIn('id', $ids)
            ->get();

        $targetIds = [];
        foreach ($existing as $row) {
            $targetIds[] = (int)($row['id'] ?? 0);
        }
        $targetIds = array_values(array_filter($targetIds, static fn (int $id): bool => $id > 0));

        if ($targetIds === []) {
            $this->redirect($this->listUrl($type), 'warning', 'Žádné platné položky pro hromadnou akci.');
        }

        $count = count($targetIds);
        try {
            switch ($action) {
                case 'publish':
                case 'draft':
                    DB::query()->table('posts')
                        ->update(['status' => $action])
                        ->whereIn('id', $targetIds)
                        ->execute();
                    $message = $action === 'publish'
                        ? 'Položky byly publikovány.'
                        : 'Položky byly přepnuty na koncept.';
                    break;

                case 'delete':
                    DB::query()->table('post_terms')
                        ->delete()
                        ->whereIn('post_id', $targetIds)
                        ->execute();
                    DB::query()->table('post_media')
                        ->delete()
                        ->whereIn('post_id', $targetIds)
                        ->execute();
                    DB::query()->table('comments')
                        ->delete()
                        ->whereIn('post_id', $targetIds)
                        ->execute();
                    DB::query()->table('posts')
                        ->delete()
                        ->whereIn('id', $targetIds)
                        ->execute();
                    $message = 'Položky byly odstraněny.';
                    break;

                default:
                    $this->redirect($this->listUrl($type), 'warning', 'Neznámá hromadná akce.');
            }
        } catch (\Throwable $e) {
            $this->redirect($this->listUrl($type), 'danger', $e->getMessage());
        }

        $this->redirect(
            $this->listUrl($type),
            'success',
            $message . ' (' . $count . ')'
        );
    }

    private function form(): void
    {
        $id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $row = null;
        $type = $this->requestedType();
        if ($id > 0) {
            $row = (new PostsRepository())->find($id);
            if (!$row) {
                $this->redirect($this->listUrl($type), 'danger', 'Příspěvek nebyl nalezen.');
            }
            $rowType = (string)($row['type'] ?? 'post');
            if (array_key_exists($rowType, $this->typeConfig())) {
                $type = $rowType;
            }
        }

        $terms = $this->termsData($id ?: null, $type);

        $this->renderAdmin('posts/edit', [
            'pageTitle'      => $this->typeConfig()[$type][$id ? 'edit' : 'create'],
            'nav'            => AdminNavigation::build('posts:' . $type),
            'post'           => $row,
            'terms'          => $terms['byType'],
            'selected'       => $terms['selected'],
            'type'           => $type,
            'types'          => $this->typeConfig(),
            'attachedMedia'  => $this->attachedMediaIds($id),
        ]);
    }

    private function listUrl(string $type): string
    {
        return 'admin.php?r=posts&type=' . urlencode($type);
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
            if ($type === 'post') {
                $catIds = isset($_POST['categories']) ? (array)$_POST['categories'] : [];
                $tagIds = isset($_POST['tags']) ? (array)$_POST['tags'] : [];

                $newCatNames = $this->parseNewTerms((string)($_POST['new_categories'] ?? ''));
                $newTagNames = $this->parseNewTerms((string)($_POST['new_tags'] ?? ''));

                if ($newCatNames !== []) {
                    $catIds = array_merge($catIds, $this->createNewTerms($newCatNames, 'category'));
                }
                if ($newTagNames !== []) {
                    $tagIds = array_merge($tagIds, $this->createNewTerms($newTagNames, 'tag'));
                }
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

            $svc = new PostsService();
            $postId = $svc->create([
                'title'        => $title,
                'type'         => $type,
                'status'       => $status,
                'content'      => $content,
                'author_id'    => (int)$user['id'],
                'thumbnail_id' => $thumbId,
            ]);

            if ($commentsAllowed === 0) {
                $svc->update($postId, ['comments_allowed'=>0]);
            }

            $this->syncPostMedia($postId, $this->parseAttachedMedia((string)($_POST['attached_media'] ?? '')));

            // ulož vazby termů
            if ($type === 'post') {
                $this->syncTerms($postId, $catIds, $tagIds);
            }

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

        $post = (new PostsRepository())->find($id);
        if (!$post) {
            $this->redirect($this->listUrl($type), 'danger', 'Příspěvek nebyl nalezen.');
        }
        $rowType = (string)($post['type'] ?? '');
        if (array_key_exists($rowType, $this->typeConfig())) {
            $type = $rowType;
        }

        try {
            $user = $this->auth->user();
            if (!$user) throw new \RuntimeException('Nejste přihlášeni.');

            $upd = [
                'title'            => (string)($_POST['title'] ?? ''),
                'status'           => (string)($_POST['status'] ?? 'draft'),
                'content'          => (string)($_POST['content'] ?? ''),
                'comments_allowed' => $type === 'post' ? (isset($_POST['comments_allowed']) ? 1 : 0) : 0,
            ];
            if (isset($_POST['slug']) && trim((string)$_POST['slug']) !== '') {
                $upd['slug'] = (string)$_POST['slug'];
            }

            // terms z formuláře
            $catIds = [];
            $tagIds = [];
            if ($type === 'post') {
                $catIds = isset($_POST['categories']) ? (array)$_POST['categories'] : [];
                $tagIds = isset($_POST['tags']) ? (array)$_POST['tags'] : [];

                $newCatNames = $this->parseNewTerms((string)($_POST['new_categories'] ?? ''));
                $newTagNames = $this->parseNewTerms((string)($_POST['new_tags'] ?? ''));

                if ($newCatNames !== []) {
                    $catIds = array_merge($catIds, $this->createNewTerms($newCatNames, 'category'));
                }
                if ($newTagNames !== []) {
                    $tagIds = array_merge($tagIds, $this->createNewTerms($newTagNames, 'tag'));
                }
            }

            // případný nový thumbnail
            $selectedThumbId = isset($_POST['selected_thumbnail_id']) ? (int)$_POST['selected_thumbnail_id'] : 0;
            $removeThumb = isset($_POST['remove_thumbnail']) && (int)$_POST['remove_thumbnail'] === 1;

            if ($removeThumb) {
                $upd['thumbnail_id'] = null;
            }

            if (!$removeThumb && !empty($_FILES['thumbnail']) && (int)$_FILES['thumbnail']['error'] !== UPLOAD_ERR_NO_FILE) {
                $mediaSvc = new MediaService();
                $up = $mediaSvc->uploadAndCreate($_FILES['thumbnail'], (int)$user['id'], $this->uploadPaths(), 'posts');
                $upd['thumbnail_id'] = (int)$up['id'];
            } elseif (!$removeThumb && $selectedThumbId > 0) {
                $upd['thumbnail_id'] = $selectedThumbId;
            }

            (new PostsService())->update($id, $upd);

            $this->syncPostMedia($id, $this->parseAttachedMedia((string)($_POST['attached_media'] ?? '')));

            // ulož vazby termů (přepíše existující)
            if ($type === 'post') {
                $this->syncTerms($id, $catIds, $tagIds);
            } else {
                $this->syncTerms($id, [], []);
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
            $repo = new PostsRepository();

            $rawId = $_POST['id'] ?? $_POST['post_id'] ?? null;
            $id = is_scalar($rawId) ? (int)$rawId : 0;
            $title = (string)($_POST['title'] ?? '');
            $content = (string)($_POST['content'] ?? '');

            $status = (string)($_POST['status'] ?? 'draft');
            $allowedStatus = ['draft', 'publish'];
            if (!in_array($status, $allowedStatus, true)) {
                $status = 'draft';
            }

            $commentsAllowed = $requestedType === 'post' ? (isset($_POST['comments_allowed']) ? 1 : 0) : 0;
            $selectedThumbId = isset($_POST['selected_thumbnail_id']) ? (int)$_POST['selected_thumbnail_id'] : 0;
            $removeThumb = isset($_POST['remove_thumbnail']) && (int)$_POST['remove_thumbnail'] === 1;

            $catIds = [];
            $tagIds = [];
            $newCatNames = [];
            $newTagNames = [];
            if ($requestedType === 'post') {
                $catIds = isset($_POST['categories']) ? (array)$_POST['categories'] : [];
                $tagIds = isset($_POST['tags']) ? (array)$_POST['tags'] : [];
                $newCatNames = $this->parseNewTerms((string)($_POST['new_categories'] ?? ''));
                $newTagNames = $this->parseNewTerms((string)($_POST['new_tags'] ?? ''));
            }

            if ($newCatNames !== []) {
                $catIds = array_merge($catIds, $this->createNewTerms($newCatNames, 'category'));
            }
            if ($newTagNames !== []) {
                $tagIds = array_merge($tagIds, $this->createNewTerms($newTagNames, 'tag'));
            }

            $attachedMedia = $this->parseAttachedMedia((string)($_POST['attached_media'] ?? ''));

            $now = DateTimeFactory::nowString();
            $type = $requestedType;
            $currentStatus = $status;

            if ($id > 0) {
                $existing = $repo->find($id);
                if (!$existing) {
                    throw new \RuntimeException('Položku se nepodařilo načíst.');
                }

                $rowType = (string)($existing['type'] ?? '');
                if ($rowType !== '' && array_key_exists($rowType, $this->typeConfig())) {
                    $type = $rowType;
                }

                $currentStatus = (string)($existing['status'] ?? $currentStatus);

                if ($type !== 'post') {
                    $commentsAllowed = 0;
                }

                $updates = [
                    'title'            => $title,
                    'content'          => $content,
                    'comments_allowed' => $commentsAllowed,
                    'updated_at'       => $now,
                ];

                if ($removeThumb) {
                    $updates['thumbnail_id'] = null;
                } elseif ($selectedThumbId > 0) {
                    $updates['thumbnail_id'] = $selectedThumbId;
                }

                if (isset($_POST['slug']) && trim((string)$_POST['slug']) !== '') {
                    $updates['slug'] = Slugger::uniqueInPosts((string)$_POST['slug'], $type, $id);
                }

                if ($status !== $currentStatus) {
                    $updates['status'] = $status;
                    $currentStatus = $status;
                }

                $repo->update($id, $updates);
            } else {
                $contentPlain = trim(strip_tags($content));
                $hasMeaningful = $title !== ''
                    || $contentPlain !== ''
                    || $attachedMedia !== []
                    || $selectedThumbId > 0
                    || $commentsAllowed === 0
                    || $catIds !== []
                    || $tagIds !== []
                    || $status !== 'draft';

                if (!$hasMeaningful) {
                    echo json_encode([
                        'success' => false,
                        'message' => '',
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    return;
                }

                $type = $requestedType;
                $slugSource = $title !== '' ? $title : ('koncept-' . bin2hex(random_bytes(3)));
                $slug = Slugger::uniqueInPosts($slugSource, $type);

                if ($type !== 'post') {
                    $commentsAllowed = 0;
                }

                $data = [
                    'title'            => $title,
                    'slug'             => $slug,
                    'type'             => $type,
                    'status'           => 'draft',
                    'content'          => $content,
                    'author_id'        => (int)$user['id'],
                    'thumbnail_id'     => $removeThumb ? null : ($selectedThumbId > 0 ? $selectedThumbId : null),
                    'comments_allowed' => $commentsAllowed,
                    'published_at'     => null,
                    'created_at'       => $now,
                    'updated_at'       => null,
                ];

                $id = $repo->create($data);
                $currentStatus = 'draft';
            }

            if ($id <= 0) {
                throw new \RuntimeException('Nepodařilo se uložit koncept.');
            }

            $this->syncPostMedia($id, $attachedMedia);
            if ($type === 'post') {
                $this->syncTerms($id, $catIds, $tagIds);
            } else {
                $this->syncTerms($id, [], []);
            }

            $statusLabels = ['draft' => 'Koncept', 'publish' => 'Publikováno'];
            $statusLabel = $statusLabels[$currentStatus] ?? ucfirst($currentStatus);

            $response = [
                'success'     => true,
                'message'     => 'Automaticky uloženo v ' . date('H:i:s'),
                'postId'      => $id,
                'status'      => $currentStatus,
                'statusLabel' => $statusLabel,
                'actionUrl'   => 'admin.php?' . http_build_query(['r' => 'posts', 'a' => 'edit', 'id' => $id, 'type' => $type]),
                'type'        => $type,
            ];

            if (isset($updates['slug']) && $updates['slug'] !== '') {
                $response['slug'] = $updates['slug'];
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

    private function createNewTerms(array $names, string $type): array
    {
        if ($names === []) {
            return [];
        }

        $repo = new TermsRepository();
        $svc  = new TermsService($repo);

        $ids = [];
        foreach ($names as $name) {
            $existing = $repo->findByNameAndType($name, $type);
            if ($existing) {
                $ids[] = (int)$existing['id'];
                continue;
            }

            $ids[] = $svc->create($name, $type);
        }

        return $ids;
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

    private function syncPostMedia(int $postId, array $mediaIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $mediaIds), static fn (int $id): bool => $id > 0)));

        DB::query()->table('post_media')
            ->delete()
            ->where('post_id', '=', $postId)
            ->execute();

        if ($ids === []) {
            return;
        }

        $ins = DB::query()->table('post_media')->insert(['post_id', 'media_id']);
        $hasRows = false;
        foreach ($ids as $mediaId) {
            $ins->values([$postId, $mediaId]);
            $hasRows = true;
        }
        if ($hasRows) {
            $ins->execute();
        }
    }

    private function delete(): void
    {
        $this->assertCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $type = $this->requestedType();
        if ($id <= 0) {
            $this->redirect($this->listUrl($type), 'danger', 'Chybí ID.');
        }

        $post = (new PostsRepository())->find($id);
        if ($post) {
            $rowType = (string)($post['type'] ?? '');
            if (array_key_exists($rowType, $this->typeConfig())) {
                $type = $rowType;
            }
        }

        // smaž vazby i post
        DB::query()->table('post_terms')->delete()->where('post_id','=', $id)->execute();
        DB::query()->table('post_media')->delete()->where('post_id','=', $id)->execute();
        DB::query()->table('comments')->delete()->where('post_id', '=', $id)->execute();
        (new PostsRepository())->delete($id);

        $this->redirect($this->listUrl($type), 'success', 'Příspěvek byl odstraněn.');
    }

    private function toggleStatus(): void
    {
        $this->assertCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $type = $this->requestedType();
        if ($id <= 0) {
            $this->redirect($this->listUrl($type), 'danger', 'Chybí ID.');
        }

        $post = (new PostsRepository())->find($id);
        if (!$post) {
            $this->redirect($this->listUrl($type), 'danger', 'Příspěvek nenalezen.');
        }
        $rowType = (string)($post['type'] ?? 'post');
        if (array_key_exists($rowType, $this->typeConfig())) {
            $type = $rowType;
        }

        $new = ((string)$post['status'] === 'publish') ? 'draft' : 'publish';
        (new PostsService())->update($id, ['status'=>$new]);

        $this->redirect(
            $this->listUrl($type),
            'success',
            $new === 'publish' ? 'Publikováno.' : 'Přepnuto na draft.'
        );
    }
}
