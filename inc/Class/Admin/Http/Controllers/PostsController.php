<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Controllers;

use Cms\Admin\Domain\PostTypes\PostTypeRegistry;
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
    /**
     * @var array<string,array{nav:string,list:string,create:string,edit:string,label:string,icon:string,supports:array<int,string>}>|null
     */
    private ?array $postTypesCache = null;

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
        if ($this->postTypesCache === null) {
            $registered = PostTypeRegistry::all();
            if ($registered === []) {
                throw new \RuntimeException('No post types have been registered.');
            }
            $this->postTypesCache = $registered;
        }

        return $this->postTypesCache;
    }

    private function requestedType(): string
    {
        $types = $this->typeConfig();
        $requested = isset($_GET['type']) ? (string)$_GET['type'] : '';
        if ($requested !== '' && array_key_exists($requested, $types)) {
            return $requested;
        }

        $fallback = array_key_first($types);
        if ($fallback === null) {
            throw new \RuntimeException('No post types have been registered.');
        }

        return $fallback;
    }

    /**
     * @return array{nav:string,list:string,create:string,edit:string,label:string,icon:string,supports:array<int,string>}
     */
    private function currentTypeConfig(string $type): array
    {
        $types = $this->typeConfig();
        if (!array_key_exists($type, $types)) {
            $fallback = array_key_first($types);
            if ($fallback === null) {
                throw new \RuntimeException('No post types have been registered.');
            }

            $type = $fallback;
        }

        return $types[$type];
    }

    /**
     * @param array{supports:array<int,string>} $typeConfig
     */
    private function typeConfigSupports(array $typeConfig, string $feature): bool
    {
        return in_array($feature, $typeConfig['supports'] ?? [], true);
    }

    private function typeSupports(string $type, string $feature): bool
    {
        $config = $this->currentTypeConfig($type);

        return $this->typeConfigSupports($config, $feature);
    }

    /**
     * @param array{supports:array<int,string>} $typeConfig
     */
    private function taxonomySupported(array $typeConfig, string $taxonomy): bool
    {
        if ($this->typeConfigSupports($typeConfig, 'terms')) {
            return true;
        }

        return $this->typeConfigSupports($typeConfig, 'terms:' . $taxonomy);
    }

    /**
     * @return array<int,string>|null
     */
    private function supportedTaxonomies(array $typeConfig): ?array
    {
        if ($this->typeConfigSupports($typeConfig, 'terms')) {
            return null; // all taxonomies are allowed
        }

        $taxonomies = [];
        foreach ($typeConfig['supports'] ?? [] as $feature) {
            if (str_starts_with($feature, 'terms:')) {
                $slug = substr($feature, strlen('terms:'));
                if ($slug !== '') {
                    $taxonomies[$slug] = $slug;
                }
            }
        }

        return array_values($taxonomies);
    }

    /** Načte seznam termů rozdělený podle typu + aktuálně vybrané pro daný post. */
    private function termsData(?int $postId, string $type): array
    {
        $typeConfig = $this->currentTypeConfig($type);
        $allowedTaxonomies = $this->supportedTaxonomies($typeConfig);

        $byType = ['category' => [], 'tag' => []];
        $selected = ['category' => [], 'tag' => []];

        if ($allowedTaxonomies === []) {
            return ['byType' => $byType, 'selected' => $selected];
        }

        $termQuery = DB::query()->table('terms', 't')
            ->select(['t.id', 't.name', 't.slug', 't.type'])
            ->orderBy('t.type', 'ASC')
            ->orderBy('t.name', 'ASC');
        if ($allowedTaxonomies !== null) {
            $termQuery->whereIn('t.type', $allowedTaxonomies);
            foreach ($allowedTaxonomies as $taxonomy) {
                if (!array_key_exists($taxonomy, $byType)) {
                    $byType[$taxonomy] = [];
                }
                if (!array_key_exists($taxonomy, $selected)) {
                    $selected[$taxonomy] = [];
                }
            }
        }

        $all = $termQuery->get();
        foreach ($all as $t) {
            $termType = (string)($t['type'] ?? '');
            if ($termType === '') {
                continue;
            }
            if ($allowedTaxonomies !== null && !in_array($termType, $allowedTaxonomies, true)) {
                continue;
            }
            if (!array_key_exists($termType, $byType)) {
                $byType[$termType] = [];
            }
            $byType[$termType][] = $t;
        }

        if ($postId) {
            $selectedQuery = DB::query()->table('post_terms', 'pt')
                ->select(['pt.term_id', 't.type'])
                ->join('terms t', 'pt.term_id', '=', 't.id')
                ->where('pt.post_id', '=', $postId);
            if ($allowedTaxonomies !== null) {
                $selectedQuery->whereIn('t.type', $allowedTaxonomies);
            }
            $rows = $selectedQuery->get();
            foreach ($rows as $r) {
                $termType = (string)($r['type'] ?? '');
                if ($termType === '') {
                    continue;
                }
                if ($allowedTaxonomies !== null && !in_array($termType, $allowedTaxonomies, true)) {
                    continue;
                }
                if (!array_key_exists($termType, $selected)) {
                    $selected[$termType] = [];
                }
                $selected[$termType][] = (int)($r['term_id'] ?? 0);
            }
        }

        foreach ($selected as $taxonomy => $ids) {
            $selected[$taxonomy] = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        }

        return ['byType' => $byType, 'selected' => $selected];
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

    /**
     * @param array<string,array<int,mixed>> $termsByTaxonomy
     */
    private function syncTerms(string $type, int $postId, array $termsByTaxonomy): void
    {
        $typeConfig = $this->currentTypeConfig($type);
        $allowedTaxonomies = $this->supportedTaxonomies($typeConfig);
        $allowAll = $allowedTaxonomies === null;

        DB::query()->table('post_terms')->delete()->where('post_id', '=', $postId)->execute();

        if (!$allowAll && $allowedTaxonomies === []) {
            return;
        }

        $ins = DB::query()->table('post_terms')->insert(['post_id', 'term_id']);
        $hasRows = false;
        foreach ($termsByTaxonomy as $taxonomy => $ids) {
            if (!$allowAll && !in_array($taxonomy, $allowedTaxonomies, true)) {
                continue;
            }
            if (!is_array($ids)) {
                continue;
            }
            foreach ($ids as $id) {
                $tid = (int)$id;
                if ($tid > 0) {
                    $ins->values([$postId, $tid]);
                    $hasRows = true;
                }
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
        $typeConfig = $this->currentTypeConfig($type);
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
                    'typeConfig'   => $typeConfig,
                    'urls'         => $urls,
                    'statusCounts' => $statusCounts,
                    'buildUrl'     => $buildUrl,
                ]),
                'table' => $this->renderPartial('posts/partials/table', [
                    'items' => $items,
                    'csrf'  => $this->token(),
                    'type'  => $type,
                    'typeConfig' => $typeConfig,
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
            'pageTitle'  => $typeConfig['list'],
            'nav'        => AdminNavigation::build('posts:' . $type),
            'filters'    => $filters,
            'items'      => $items,
            'pagination' => $pagination,
            'type'       => $type,
            'types'      => $types,
            'typeConfig' => $typeConfig,
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

    /**
     * @param array<int> $affectedIds
     */
    private function respondListingAction(string $type, bool $success, string $flashType, string $message, array $affectedIds, ?string $nextState, int $status = 200): never
    {
        $normalizedIds = [];
        foreach ($affectedIds as $id) {
            $value = (int)$id;
            if ($value > 0) {
                $normalizedIds[] = $value;
            }
        }

        $payload = [
            'success'     => $success,
            'flash'       => [
                'type' => $flashType,
                'msg'  => $message,
            ],
            'affectedIds' => $normalizedIds,
            'nextState'   => $nextState,
            'type'        => $type,
        ];

        if ($success) {
            try {
                $payload['statusCounts'] = (new PostsRepository())->countByStatus($type);
            } catch (\Throwable $e) {
                // ignore count errors in the AJAX response
            }
        }

        if ($this->isAjax()) {
            $this->jsonResponse($payload, $status);
        }

        $this->redirect($this->listUrl($type), $flashType, $message);
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
            $this->respondListingAction($type, false, 'warning', 'Vyberte položky a požadovanou akci.', [], null, 422);
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
            $this->respondListingAction($type, false, 'warning', 'Žádné platné položky pro hromadnou akci.', [], null, 404);
        }

        $affected = $targetIds;
        $nextState = null;
        $message = '';

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
                    $nextState = $action;
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
                    $nextState = 'deleted';
                    break;

                default:
                    $this->respondListingAction($type, false, 'warning', 'Neznámá hromadná akce.', [], null, 400);
            }
        } catch (\Throwable $e) {
            $this->respondListingAction($type, false, 'danger', $e->getMessage(), [], null, 500);
        }

        $count = count($affected);
        $this->respondListingAction(
            $type,
            true,
            'success',
            $message . ' (' . $count . ')',
            $affected,
            $nextState
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
        $types = $this->typeConfig();
        $typeConfig = $this->currentTypeConfig($type);

        $deleteUrl = 'admin.php?' . http_build_query([
            'r' => 'posts',
            'a' => 'delete',
            'type' => $type,
        ]);
        $deleteCsrf = $this->token();
        $publicUrl = null;

        if ($row) {
            $slug = trim((string)($row['slug'] ?? ''));
            if ($slug !== '') {
                $links = new LinkGenerator();
                $publicUrl = $links->postOfType($type, $slug);
            }
        }

        $this->renderAdmin('posts/edit', [
            'pageTitle'      => $typeConfig[$id ? 'edit' : 'create'],
            'nav'            => AdminNavigation::build('posts:' . $type),
            'post'           => $row,
            'terms'          => $terms['byType'],
            'selected'       => $terms['selected'],
            'type'           => $type,
            'types'          => $types,
            'typeConfig'     => $typeConfig,
            'attachedMedia'  => $this->attachedMediaIds($id),
            'publicUrl'      => $publicUrl,
            'deleteUrl'      => $deleteUrl,
            'deleteCsrf'     => $deleteCsrf,
        ]);
    }

    private function listUrl(string $type): string
    {
        return 'admin.php?r=posts&type=' . urlencode($type);
    }

    private function store(): void
    {
        $this->assertCsrf();

        $type = $this->requestedType();
        $typeConfig = $this->currentTypeConfig($type);
        $supportsComments = $this->typeConfigSupports($typeConfig, 'comments');
        $supportsThumbnail = $this->typeConfigSupports($typeConfig, 'thumbnail');
        $supportsCategory = $this->taxonomySupported($typeConfig, 'category');
        $supportsTag = $this->taxonomySupported($typeConfig, 'tag');
        $user = $this->auth->user();
        if (!$user) {
            $this->respondPostFormResult(
                false,
                'Nejste přihlášeni.',
                [],
                ['form' => ['Nejste přihlášeni.']],
                null,
                'admin.php?r=posts&a=create&type=' . $type,
                401
            );
        }

        $title   = trim((string)($_POST['title'] ?? ''));
        $status  = (string)($_POST['status'] ?? 'draft');
        $content = (string)($_POST['content'] ?? '');
        $commentsAllowed = $supportsComments && isset($_POST['comments_allowed']) ? 1 : 0;

        $validationErrors = [];
        if ($title === '') {
            $validationErrors['title'][] = 'Titulek je povinný.';
        }
        if (!in_array($status, ['draft', 'publish'], true)) {
            $validationErrors['form'][] = 'Neplatný stav.';
        }

        if ($validationErrors !== []) {
            $this->respondPostFormResult(
                false,
                'Opravte chyby ve formuláři.',
                [],
                $validationErrors,
                null,
                'admin.php?r=posts&a=create&type=' . $type,
                422
            );
        }

        // terms z formuláře
        $catIds = [];
        $tagIds = [];
        if ($supportsCategory) {
            $catIds = isset($_POST['categories']) ? (array)$_POST['categories'] : [];
            $newCatNames = $this->parseNewTerms((string)($_POST['new_categories'] ?? ''));
            if ($newCatNames !== []) {
                $catIds = array_merge($catIds, $this->createNewTerms($newCatNames, 'category'));
            }
        }
        if ($supportsTag) {
            $tagIds = isset($_POST['tags']) ? (array)$_POST['tags'] : [];
            $newTagNames = $this->parseNewTerms((string)($_POST['new_tags'] ?? ''));
            if ($newTagNames !== []) {
                $tagIds = array_merge($tagIds, $this->createNewTerms($newTagNames, 'tag'));
            }
        }

        $selectedThumbId = $supportsThumbnail ? (int)($_POST['selected_thumbnail_id'] ?? 0) : 0;
        $removeThumb = $supportsThumbnail && isset($_POST['remove_thumbnail']) && (int)$_POST['remove_thumbnail'] === 1;
        $thumbId = null;
        if ($supportsThumbnail && !$removeThumb) {
            if (!empty($_FILES['thumbnail']) && (int)$_FILES['thumbnail']['error'] !== UPLOAD_ERR_NO_FILE) {
                $mediaSvc = new MediaService();
                $up = $mediaSvc->uploadAndCreate($_FILES['thumbnail'], (int)$user['id'], $this->uploadPaths(), 'posts');
                $thumbId = (int)$up['id'];
            } elseif ($selectedThumbId > 0) {
                $thumbId = $selectedThumbId;
            }
        }

        $attachedMedia = $this->parseAttachedMedia((string)($_POST['attached_media'] ?? ''));

        try {
            $svc = new PostsService();
            $payload = [
                'title'        => $title,
                'type'         => $type,
                'status'       => $status,
                'content'      => $content,
                'author_id'    => (int)$user['id'],
            ];
            if ($supportsThumbnail) {
                $payload['thumbnail_id'] = $thumbId;
            }
            if ($supportsComments) {
                $payload['comments_allowed'] = $commentsAllowed;
            }

            $postId = $svc->create($payload);

            $this->syncPostMedia($type, $postId, $attachedMedia);

            $termsToSync = [];
            if ($supportsCategory) {
                $termsToSync['category'] = $catIds;
            }
            if ($supportsTag) {
                $termsToSync['tag'] = $tagIds;
            }
            $this->syncTerms($type, $postId, $termsToSync);

            $repo = new PostsRepository();
            $row = $repo->find($postId) ?: [];
            $slug = (string)($row['slug'] ?? '');

            $redirect = 'admin.php?' . http_build_query([
                'r'    => 'posts',
                'a'    => 'edit',
                'id'   => $postId,
                'type' => $type,
            ]);

            $this->respondPostFormResult(
                true,
                'Příspěvek byl vytvořen.',
                $this->buildPostResponseData($postId, $type, $status, $slug, $redirect),
                [],
                $redirect
            );
        } catch (\InvalidArgumentException $e) {
            $errors = $this->decodeValidationErrors($e->getMessage());
            $this->respondPostFormResult(
                false,
                'Opravte chyby ve formuláři.',
                [],
                $errors,
                null,
                'admin.php?r=posts&a=create&type=' . $type,
                422
            );
        } catch (\Throwable $e) {
            $this->respondPostFormResult(
                false,
                $e->getMessage(),
                [],
                ['form' => [$e->getMessage()]],
                null,
                'admin.php?r=posts&a=create&type=' . $type,
                500
            );
        }
    }

    private function update(): void
    {
        $this->assertCsrf();
        $id = (int)($_GET['id'] ?? 0);
        $type = $this->requestedType();
        if ($id <= 0) {
            $this->respondPostFormResult(
                false,
                'Chybí ID.',
                [],
                ['form' => ['Chybí ID.']],
                null,
                $this->listUrl($type),
                422
            );
        }

        $post = (new PostsRepository())->find($id);
        if (!$post) {
            $this->respondPostFormResult(
                false,
                'Příspěvek nebyl nalezen.',
                [],
                ['form' => ['Příspěvek nebyl nalezen.']],
                null,
                $this->listUrl($type),
                404
            );
        }
        $rowType = (string)($post['type'] ?? '');
        if (array_key_exists($rowType, $this->typeConfig())) {
            $type = $rowType;
        }
        $typeConfig = $this->currentTypeConfig($type);
        $supportsComments = $this->typeConfigSupports($typeConfig, 'comments');
        $supportsThumbnail = $this->typeConfigSupports($typeConfig, 'thumbnail');
        $supportsCategory = $this->taxonomySupported($typeConfig, 'category');
        $supportsTag = $this->taxonomySupported($typeConfig, 'tag');

        try {
            $user = $this->auth->user();
            if (!$user) throw new \RuntimeException('Nejste přihlášeni.');

            $title = trim((string)($_POST['title'] ?? ''));
            $status = (string)($_POST['status'] ?? 'draft');
            $content = (string)($_POST['content'] ?? '');
            $slugInput = isset($_POST['slug']) ? trim((string)$_POST['slug']) : '';
            $commentsAllowed = $supportsComments && isset($_POST['comments_allowed']) ? 1 : 0;

            $validationErrors = [];
            if ($title === '') {
                $validationErrors['title'][] = 'Titulek je povinný.';
            }
            if (!in_array($status, ['draft', 'publish'], true)) {
                $validationErrors['form'][] = 'Neplatný stav.';
            }

            if ($validationErrors !== []) {
                $this->respondPostFormResult(
                    false,
                    'Opravte chyby ve formuláři.',
                    [],
                    $validationErrors,
                    null,
                    'admin.php?r=posts&a=edit&id=' . $id . '&type=' . $type,
                    422
                );
            }

            $upd = [
                'title'   => $title,
                'status'  => $status,
                'content' => $content,
            ];
            if ($supportsComments || array_key_exists('comments_allowed', $post)) {
                $upd['comments_allowed'] = $supportsComments ? $commentsAllowed : 0;
            }
            if ($slugInput !== '') {
                $upd['slug'] = $slugInput;
            }

            // terms z formuláře
            $catIds = [];
            $tagIds = [];
            if ($supportsCategory) {
                $catIds = isset($_POST['categories']) ? (array)$_POST['categories'] : [];
                $newCatNames = $this->parseNewTerms((string)($_POST['new_categories'] ?? ''));
                if ($newCatNames !== []) {
                    $catIds = array_merge($catIds, $this->createNewTerms($newCatNames, 'category'));
                }
            }
            if ($supportsTag) {
                $tagIds = isset($_POST['tags']) ? (array)$_POST['tags'] : [];
                $newTagNames = $this->parseNewTerms((string)($_POST['new_tags'] ?? ''));
                if ($newTagNames !== []) {
                    $tagIds = array_merge($tagIds, $this->createNewTerms($newTagNames, 'tag'));
                }
            }

            // případný nový thumbnail
            $selectedThumbId = $supportsThumbnail ? (int)($_POST['selected_thumbnail_id'] ?? 0) : 0;
            $removeThumb = $supportsThumbnail && isset($_POST['remove_thumbnail']) && (int)$_POST['remove_thumbnail'] === 1;

            if ($supportsThumbnail) {
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
            } else {
                $upd['thumbnail_id'] = null;
            }

            $service = new PostsService();
            $service->update($id, $upd);

            $this->syncPostMedia($type, $id, $this->parseAttachedMedia((string)($_POST['attached_media'] ?? '')));

            // ulož vazby termů (přepíše existující)
            $termsToSync = [];
            if ($supportsCategory) {
                $termsToSync['category'] = $catIds;
            }
            if ($supportsTag) {
                $termsToSync['tag'] = $tagIds;
            }
            $this->syncTerms($type, $id, $termsToSync);

            $repo = new PostsRepository();
            $fresh = $repo->find($id) ?: [];
            $resolvedType = (string)($fresh['type'] ?? $type);
            if (!array_key_exists($resolvedType, $this->typeConfig())) {
                $resolvedType = $type;
            }
            $resolvedStatus = (string)($fresh['status'] ?? $status);
            $resolvedSlug = (string)($fresh['slug'] ?? $slugInput);

            $editUrl = 'admin.php?' . http_build_query([
                'r'    => 'posts',
                'a'    => 'edit',
                'id'   => $id,
                'type' => $resolvedType,
            ]);

            $this->respondPostFormResult(
                true,
                'Změny byly uloženy.',
                $this->buildPostResponseData($id, $resolvedType, $resolvedStatus, $resolvedSlug, $editUrl),
                [],
                null,
                $editUrl
            );

        } catch (\Throwable $e) {
            if ($e instanceof \InvalidArgumentException) {
                $errors = $this->decodeValidationErrors($e->getMessage());
                $this->respondPostFormResult(
                    false,
                    'Opravte chyby ve formuláři.',
                    [],
                    $errors,
                    null,
                    'admin.php?r=posts&a=edit&id=' . $id . '&type=' . $type,
                    422
                );
            }

            $this->respondPostFormResult(
                false,
                $e->getMessage(),
                [],
                ['form' => [$e->getMessage()]],
                null,
                'admin.php?r=posts&a=edit&id=' . $id . '&type=' . $type,
                500
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
                'data'    => [],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        try {
            $user = $this->auth->user();
            if (!$user) {
                throw new \RuntimeException('Nejste přihlášeni.');
            }

            $requestedType = $this->requestedType();
            $typeConfig = $this->currentTypeConfig($requestedType);
            $supportsComments = $this->typeConfigSupports($typeConfig, 'comments');
            $supportsThumbnail = $this->typeConfigSupports($typeConfig, 'thumbnail');
            $supportsCategory = $this->taxonomySupported($typeConfig, 'category');
            $supportsTag = $this->taxonomySupported($typeConfig, 'tag');
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

            $commentsAllowed = $supportsComments && isset($_POST['comments_allowed']) ? 1 : 0;
            $selectedThumbId = $supportsThumbnail ? (int)($_POST['selected_thumbnail_id'] ?? 0) : 0;
            $removeThumb = $supportsThumbnail && isset($_POST['remove_thumbnail']) && (int)$_POST['remove_thumbnail'] === 1;

            $catIds = [];
            $tagIds = [];
            $newCatNames = [];
            $newTagNames = [];
            if ($supportsCategory) {
                $catIds = isset($_POST['categories']) ? (array)$_POST['categories'] : [];
                $newCatNames = $this->parseNewTerms((string)($_POST['new_categories'] ?? ''));
            }
            if ($supportsTag) {
                $tagIds = isset($_POST['tags']) ? (array)$_POST['tags'] : [];
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
            $currentSlug = null;

            if ($id > 0) {
                $existing = $repo->find($id);
                if (!$existing) {
                    throw new \RuntimeException('Položku se nepodařilo načíst.');
                }

                $rowType = (string)($existing['type'] ?? '');
                if ($rowType !== '' && array_key_exists($rowType, $this->typeConfig())) {
                    $type = $rowType;
                    $typeConfig = $this->currentTypeConfig($type);
                    $supportsComments = $this->typeConfigSupports($typeConfig, 'comments');
                    $supportsThumbnail = $this->typeConfigSupports($typeConfig, 'thumbnail');
                    $supportsCategory = $this->taxonomySupported($typeConfig, 'category');
                    $supportsTag = $this->taxonomySupported($typeConfig, 'tag');
                    if (!$supportsCategory) {
                        $catIds = [];
                    }
                    if (!$supportsTag) {
                        $tagIds = [];
                    }
                }

                $currentStatus = (string)($existing['status'] ?? $currentStatus);
                $currentSlug = isset($existing['slug']) ? (string)$existing['slug'] : null;

                if (!$supportsComments) {
                    $commentsAllowed = 0;
                }

                $existingHasComments = is_array($existing) && array_key_exists('comments_allowed', $existing);

                $updates = [
                    'title'            => $title,
                    'content'          => $content,
                    'updated_at'       => $now,
                ];

                if ($supportsComments || $existingHasComments) {
                    $updates['comments_allowed'] = $supportsComments ? $commentsAllowed : 0;
                }

                if ($supportsThumbnail) {
                    if ($removeThumb) {
                        $updates['thumbnail_id'] = null;
                    } elseif ($selectedThumbId > 0) {
                        $updates['thumbnail_id'] = $selectedThumbId;
                    }
                } else {
                    $updates['thumbnail_id'] = null;
                }

                if (isset($_POST['slug']) && trim((string)$_POST['slug']) !== '') {
                    $updates['slug'] = Slugger::uniqueInPosts((string)$_POST['slug'], $type, $id);
                    $currentSlug = $updates['slug'];
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
                    || ($supportsThumbnail && $selectedThumbId > 0)
                    || ($supportsComments && $commentsAllowed === 0)
                    || ($supportsCategory && $catIds !== [])
                    || ($supportsTag && $tagIds !== [])
                    || $status !== 'draft';

                if (!$hasMeaningful) {
                    echo json_encode([
                        'success' => false,
                        'message' => '',
                        'data'    => [],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    return;
                }

                $type = $requestedType;
                $slugSource = $title !== '' ? $title : ('koncept-' . bin2hex(random_bytes(3)));
                $slug = Slugger::uniqueInPosts($slugSource, $type);
                $currentSlug = $slug;

                if (!$supportsComments) {
                    $commentsAllowed = 0;
                }

                $data = [
                    'title'            => $title,
                    'slug'             => $slug,
                    'type'             => $type,
                    'status'           => 'draft',
                    'content'          => $content,
                    'author_id'        => (int)$user['id'],
                    'thumbnail_id'     => $supportsThumbnail && !$removeThumb && $selectedThumbId > 0 ? $selectedThumbId : null,
                    'comments_allowed' => $supportsComments ? $commentsAllowed : 0,
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

            $this->syncPostMedia($type, $id, $attachedMedia);
            $termsToSync = [];
            if ($supportsCategory) {
                $termsToSync['category'] = $catIds;
            }
            if ($supportsTag) {
                $termsToSync['tag'] = $tagIds;
            }
            $this->syncTerms($type, $id, $termsToSync);

            $fresh = $repo->find($id) ?: [];
            $finalType = (string)($fresh['type'] ?? $type);
            if (!array_key_exists($finalType, $this->typeConfig())) {
                $finalType = $type;
            }
            $finalStatus = (string)($fresh['status'] ?? $currentStatus);
            $finalSlug = isset($fresh['slug']) ? (string)$fresh['slug'] : ($currentSlug ?? '');

            $actionUrl = 'admin.php?' . http_build_query(['r' => 'posts', 'a' => 'edit', 'id' => $id, 'type' => $finalType]);

            $response = [
                'success' => true,
                'message' => 'Automaticky uloženo v ' . date('H:i:s'),
                'data'    => $this->buildPostResponseData($id, $finalType, $finalStatus, $finalSlug, $actionUrl),
            ];

            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => [],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,array<int,string>> $errors
     */
    private function respondPostFormResult(
        bool $success,
        string $message,
        array $data,
        array $errors,
        ?string $redirect = null,
        ?string $nonAjaxRedirect = null,
        int $status = 200
    ): never {
        $payload = ['success' => $success];
        if ($message !== '') {
            $payload['message'] = $message;
        }
        if ($data !== []) {
            $payload['data'] = $data;
        }
        if ($errors !== []) {
            $payload['errors'] = $errors;
        }
        if ($redirect !== null && $redirect !== '') {
            $payload['redirect'] = $redirect;
        }

        if ($this->isAjax()) {
            $this->jsonResponse($payload, $status);
        }

        $target = $nonAjaxRedirect ?? $redirect ?? $this->listUrl($this->requestedType());
        $flashType = $success ? 'success' : 'danger';
        $flashMessage = $message !== '' ? $message : ($success ? 'Akce proběhla úspěšně.' : 'Došlo k chybě.');
        $this->redirect($target, $flashType, $flashMessage);
    }

    /**
     * @return array{post: array<string,mixed>}
     */
    private function buildPostResponseData(int $id, string $type, string $status, ?string $slug, string $actionUrl): array
    {
        $normalizedStatus = $this->normalizePostStatus($status);

        $post = [
            'id'          => $id,
            'type'        => $type,
            'status'      => $normalizedStatus,
            'statusLabel' => $this->statusLabelFor($normalizedStatus),
            'actionUrl'   => $actionUrl,
        ];

        if ($slug !== null) {
            $post['slug'] = $slug;
        }

        return ['post' => $post];
    }

    private function normalizePostStatus(string $status): string
    {
        $allowed = ['draft', 'publish'];
        $normalized = strtolower($status);
        return in_array($normalized, $allowed, true) ? $normalized : 'draft';
    }

    private function statusLabelFor(string $status): string
    {
        return match ($status) {
            'publish' => 'Publikováno',
            'draft' => 'Koncept',
            default => ucfirst($status),
        };
    }

    /**
     * @return array<string,array<int,string>>
     */
    private function decodeValidationErrors(string $message): array
    {
        $decoded = json_decode($message, true);
        if (is_array($decoded)) {
            return $this->normalizeValidationErrorsArray($decoded);
        }

        $text = trim($message);
        if ($text === '') {
            $text = 'Opravte chyby ve formuláři.';
        }

        return ['form' => [$text]];
    }

    /**
     * @param array<string|int,mixed> $errors
     * @return array<string,array<int,string>>
     */
    private function normalizeValidationErrorsArray(array $errors): array
    {
        $normalized = [];
        foreach ($errors as $field => $messages) {
            $key = is_string($field) && $field !== '' ? $field : 'form';
            $list = is_array($messages) ? $messages : [$messages];
            $collected = [];
            foreach ($list as $message) {
                $text = trim((string)$message);
                if ($text !== '') {
                    $collected[] = $text;
                }
            }
            if ($collected !== []) {
                $normalized[$key] = $collected;
            }
        }

        if ($normalized === []) {
            return ['form' => ['Opravte chyby ve formuláři.']];
        }

        return $normalized;
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

    private function syncPostMedia(string $type, int $postId, array $mediaIds): void
    {
        $typeConfig = $this->currentTypeConfig($type);
        $mediaSupported = $this->typeConfigSupports($typeConfig, 'media')
            || $this->typeConfigSupports($typeConfig, 'attachments')
            || $this->typeConfigSupports($typeConfig, 'thumbnail');

        $ids = array_values(array_unique(array_filter(array_map('intval', $mediaIds), static fn (int $id): bool => $id > 0)));

        DB::query()->table('post_media')
            ->delete()
            ->where('post_id', '=', $postId)
            ->execute();

        if (!$mediaSupported || $ids === []) {
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
            $this->respondListingAction($type, false, 'danger', 'Chybí ID.', [], null, 422);
        }

        $post = (new PostsRepository())->find($id);
        if ($post) {
            $rowType = (string)($post['type'] ?? '');
            if (array_key_exists($rowType, $this->typeConfig())) {
                $type = $rowType;
            }
        } else {
            $this->respondListingAction($type, false, 'danger', 'Příspěvek nebyl nalezen.', [], null, 404);
        }

        try {
            // smaž vazby i post
            DB::query()->table('post_terms')->delete()->where('post_id','=', $id)->execute();
            DB::query()->table('post_media')->delete()->where('post_id','=', $id)->execute();
            DB::query()->table('comments')->delete()->where('post_id', '=', $id)->execute();
            (new PostsRepository())->delete($id);
        } catch (\Throwable $e) {
            $this->respondListingAction($type, false, 'danger', $e->getMessage(), [], null, 500);
        }

        $this->respondListingAction($type, true, 'success', 'Příspěvek byl odstraněn.', [$id], 'deleted');
    }

    private function toggleStatus(): void
    {
        $this->assertCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $type = $this->requestedType();
        if ($id <= 0) {
            $this->respondListingAction($type, false, 'danger', 'Chybí ID.', [], null, 422);
        }

        $post = (new PostsRepository())->find($id);
        if (!$post) {
            $this->respondListingAction($type, false, 'danger', 'Příspěvek nenalezen.', [], null, 404);
        }
        $rowType = (string)($post['type'] ?? 'post');
        if (array_key_exists($rowType, $this->typeConfig())) {
            $type = $rowType;
        }

        $new = ((string)$post['status'] === 'publish') ? 'draft' : 'publish';
        try {
            (new PostsService())->update($id, ['status'=>$new]);
        } catch (\Throwable $e) {
            $this->respondListingAction($type, false, 'danger', $e->getMessage(), [], null, 500);
        }

        $this->respondListingAction(
            $type,
            true,
            'success',
            $new === 'publish' ? 'Publikováno.' : 'Přepnuto na draft.',
            [$id],
            $new
        );
    }
}
