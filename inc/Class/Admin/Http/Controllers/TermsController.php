<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Controllers;

use Cms\Admin\Domain\Repositories\TermsRepository;
use Cms\Admin\Domain\Services\TermsService;
use Core\Database\Init as DB;
use Cms\Admin\Utils\Slugger;
use Cms\Admin\Utils\AdminNavigation;
use Cms\Admin\Utils\LinkGenerator;

final class TermsController extends BaseAdminController
{
    public function handle(string $action): void
    {
        switch ($action) {
            case 'create':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') $this->store(); else $this->form();
                return;

            case 'edit':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') $this->update(); else $this->form();
                return;

            case 'delete':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') $this->delete(); else $this->index();
                return;

            case 'bulk':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->bulk(); return; }
                $this->index();
                return;

            case 'index':
            default:
                $this->index();
                return;
        }
    }

    // ------------- helpers -------------
    private function typeConfig(): array
    {
        return [
            'category' => [
                'nav'    => 'Kategorie',
                'list'   => 'Kategorie',
                'create' => 'Nová kategorie',
                'edit'   => 'Upravit kategorii',
                'label'  => 'Kategorie',
            ],
            'tag' => [
                'nav'    => 'Štítky',
                'list'   => 'Štítky',
                'create' => 'Nový štítek',
                'edit'   => 'Upravit štítek',
                'label'  => 'Štítek',
            ],
        ];
    }

    private function requestedType(): string
    {
        $type = (string)($_GET['type'] ?? 'category');
        if (!array_key_exists($type, $this->typeConfig())) {
            $type = 'category';
        }
        return $type;
    }

    // ------------- actions -------------

    /** Přehled + filtry */
    private function index(): void
    {
        $type = $this->requestedType();
        $filters = [
            'type' => $type,
            'q'    => (string)($_GET['q'] ?? ''),
        ];
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;

        $query = DB::query()->table('terms', 't')
            ->select(['t.id', 't.type', 't.slug', 't.name', 't.description', 't.created_at'])
            ->orderBy('t.created_at', 'DESC')
            ->where('t.type', '=', $filters['type']);

        if ($filters['q'] !== '') {
            $like = '%' . $filters['q'] . '%';
            $query->where(function ($w) use ($like) {
                $w->whereLike('t.name', $like)->orWhere('t.slug', 'LIKE', $like);
            });
        }

        $paginated = $query->paginate($page, $perPage);

        $pagination = $this->paginationData($paginated, $page, $perPage);
        $buildUrl = $this->listingUrlBuilder([
            'r'    => 'terms',
            'type' => $type,
            'q'    => $filters['q'],
        ]);

        $items = $this->normalizeCreatedAt($paginated['items'] ?? []);
        $typeConfig = $this->typeConfig();
        $urls = new LinkGenerator();

        if ($this->wantsJsonIndex()) {
            $payload = [
                'success'    => true,
                'type'       => $type,
                'filters'    => $filters,
                'pagination' => $pagination,
                'csrf'       => $this->token(),
                'items'      => $this->serializeTerms($items, $urls),
                'partials'   => [
                    'toolbar'    => $this->renderPartial('terms/partials/toolbar', [
                        'filters'  => $filters,
                        'type'     => $type,
                        'types'    => $typeConfig,
                        'buildUrl' => $buildUrl,
                    ]),
                    'table'      => $this->renderPartial('terms/partials/table', [
                        'items' => $items,
                        'type'  => $type,
                        'urls'  => $urls,
                        'csrf'  => $this->token(),
                    ]),
                    'pagination' => $this->renderPartial('terms/partials/pagination', [
                        'pagination' => $pagination,
                        'buildUrl'   => $buildUrl,
                    ]),
                ],
            ];

            $this->jsonResponse($payload);
        }

        $this->renderAdmin('terms/index', [
            'pageTitle'  => $typeConfig[$type]['list'],
            'nav'        => AdminNavigation::build('terms:' . $type),
            'filters'    => $filters,
            'items'      => $items,
            'pagination' => $pagination,
            'type'       => $type,
            'types'      => $typeConfig,
            'urls'       => $urls,
            'buildUrl'   => $buildUrl,
        ]);
    }

    /** Form create/edit */
    private function form(): void
    {
        $id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $row = null;
        $type = $this->requestedType();
        if ($id > 0) {
            $row = (new TermsRepository())->find($id);
            if (!$row) {
                $this->redirect('admin.php?r=terms&type=' . urlencode($type), 'danger', 'Term nenalezen.');
            }
            $type = (string)($row['type'] ?? $type);
        }

        $this->renderAdmin('terms/edit', [
            'pageTitle' => $id ? ($this->typeConfig()[$type]['edit'] ?? 'Upravit term') : ($this->typeConfig()[$type]['create'] ?? 'Nový term'),
            'nav'       => AdminNavigation::build('terms:' . $type),
            'term'      => $row,
            'type'      => $type,
            'types'     => $this->typeConfig(),
        ]);
    }

    /** Uložení nového termu */
    private function store(): void
    {
        $this->assertCsrf();

        $type = (string)($_POST['type'] ?? $this->requestedType());
        if (!array_key_exists($type, $this->typeConfig())) {
            $type = 'category';
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $slug = trim((string)($_POST['slug'] ?? ''));
        $desc = (string)($_POST['description'] ?? '');

        $errors = $this->validateTermInput($name, $slug);
        if ($errors !== []) {
            $this->respondValidationErrors($errors, 'Název je povinný.', 'admin.php?r=terms&a=create&type=' . urlencode($type));
        }

        $repo = new TermsRepository();
        $slug = Slugger::uniqueInTerms($slug !== '' ? $slug : $name, $type);

        try {
            $service = new TermsService($repo);
            $id = $service->create($name, $type, $slug, $desc);
        } catch (\InvalidArgumentException $e) {
            $decoded = $this->safeDecodeValidationErrors($e->getMessage());
            $this->respondValidationErrors($decoded, 'Název je povinný.', 'admin.php?r=terms&a=create&type=' . urlencode($type));
        } catch (\Throwable $e) {
            $this->respondFailure('Term se nepodařilo vytvořit.', 'admin.php?r=terms&a=create&type=' . urlencode($type), $e);
        }

        $term = $repo->find($id) ?: [
            'id'          => $id,
            'name'        => $name,
            'slug'        => $slug,
            'type'        => $type,
            'description' => $desc,
        ];
        $termData = $this->serializeTerm($term, new LinkGenerator());

        $redirectUrl = 'admin.php?r=terms&type=' . rawurlencode($type);

        if ($this->isAjax()) {
            $this->jsonResponse([
                'success'  => true,
                'message'  => 'Term byl vytvořen.',
                'term'     => $termData,
                'type'     => $type,
                'redirect' => $redirectUrl,
                'flash'    => ['type' => 'success', 'msg' => 'Term byl vytvořen.'],
            ]);
        }

        $this->redirect($redirectUrl, 'success', 'Term byl vytvořen.');
    }

    /** Update existujícího termu */
    private function update(): void
    {
        $this->assertCsrf();
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $type = $this->requestedType();
            $this->respondFailure('Chybí ID.', 'admin.php?r=terms&type=' . urlencode($type), null, 422);
        }

        $repo = new TermsRepository();
        $row = $repo->find($id);
        if (!$row) {
            $type = $this->requestedType();
            $this->respondFailure('Term nenalezen.', 'admin.php?r=terms&type=' . urlencode($type), null, 404);
        }

        $type = (string)($row['type'] ?? 'category');
        $name = trim((string)($_POST['name'] ?? ''));
        $slug = trim((string)($_POST['slug'] ?? ''));
        $desc = (string)($_POST['description'] ?? '');

        $errors = $this->validateTermInput($name, $slug);
        if ($errors !== []) {
            $this->respondValidationErrors($errors, 'Název je povinný.', 'admin.php?r=terms&a=edit&id=' . $id . '&type=' . urlencode($type));
        }

        $slug = Slugger::uniqueInTerms($slug !== '' ? $slug : $name, $type, $id);

        try {
            DB::query()->table('terms')->update([
                'name'        => $name,
                'slug'        => $slug,
                'type'        => $type,
                'description' => $desc,
                'created_at'  => $row['created_at'],
            ])->where('id', '=', $id)->execute();
        } catch (\Throwable $e) {
            $this->respondFailure('Term se nepodařilo uložit.', 'admin.php?r=terms&a=edit&id=' . $id . '&type=' . urlencode($type), $e);
        }

        $term = $repo->find($id) ?: array_merge($row, [
            'name'        => $name,
            'slug'        => $slug,
            'description' => $desc,
        ]);
        $termData = $this->serializeTerm($term, new LinkGenerator());

        if ($this->isAjax()) {
            $this->jsonResponse([
                'success' => true,
                'message' => 'Změny byly uloženy.',
                'term'    => $termData,
                'type'    => $type,
            ]);
        }

        $this->redirect('admin.php?r=terms&a=edit&id=' . $id . '&type=' . urlencode($type), 'success', 'Změny byly uloženy.');
    }

    /** Smazání termu (odpojí z postů) */
    private function delete(): void
    {
        $this->assertCsrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $type = $this->requestedType();
            $this->respondFailure('Chybí ID.', 'admin.php?r=terms&type=' . urlencode($type), null, 422);
        }

        $repo = new TermsRepository();
        $row = $repo->find($id);
        if (!$row) {
            $type = $this->requestedType();
            $this->respondFailure('Term nenalezen.', 'admin.php?r=terms&type=' . urlencode($type), null, 404);
        }

        DB::query()->table('post_terms')->delete()->where('term_id', '=', $id)->execute();
        DB::query()->table('terms')->delete()->where('id', '=', $id)->execute();

        $type = (string)($row['type'] ?? $this->requestedType());

        if ($this->isAjax()) {
            $this->jsonResponse([
                'success'    => true,
                'message'    => 'Term byl odstraněn.',
                'removedIds' => [$id],
                'type'       => $type,
            ]);
        }

        $this->redirect('admin.php?r=terms&type=' . urlencode($type), 'success', 'Term byl odstraněn.');
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
            $this->respondValidationErrors(['form' => ['Vyberte položky a požadovanou akci.']], 'Vyberte položky a požadovanou akci.', 'admin.php?r=terms&type=' . urlencode($type));
        }

        $existing = DB::query()->table('terms')
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
            $this->respondFailure('Žádné platné položky pro hromadnou akci.', 'admin.php?r=terms&type=' . urlencode($type), null, 404, 'warning');
        }

        try {
            if ($action === 'delete') {
                DB::query()->table('post_terms')->delete()->whereIn('term_id', $targetIds)->execute();
                DB::query()->table('terms')->delete()->whereIn('id', $targetIds)->execute();
            } else {
                $this->respondFailure('Neznámá hromadná akce.', 'admin.php?r=terms&type=' . urlencode($type), null, 422, 'warning');
            }
        } catch (\Throwable $e) {
            $this->respondFailure('Hromadná akce selhala.', 'admin.php?r=terms&type=' . urlencode($type), $e);
        }

        if ($this->isAjax()) {
            $this->jsonResponse([
                'success'    => true,
                'message'    => 'Hromadná akce dokončena (' . count($targetIds) . ')',
                'removedIds' => $action === 'delete' ? $targetIds : [],
                'type'       => $type,
            ]);
        }

        $this->redirect(
            'admin.php?r=terms&type=' . urlencode($type),
            'success',
            'Hromadná akce dokončena (' . count($targetIds) . ')'
        );
    }

    private function wantsJsonIndex(): bool
    {
        if ($this->isAjax()) {
            return true;
        }

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
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function serializeTerms(array $rows, LinkGenerator $urls): array
    {
        if ($rows === []) {
            return [];
        }

        $terms = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $terms[] = $this->serializeTerm($row, $urls);
        }

        return $terms;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function serializeTerm(array $row, LinkGenerator $urls): array
    {
        $normalized = $this->normalizeCreatedAt([$row]);
        $row = $normalized[0] ?? $row;

        $id = (int)($row['id'] ?? 0);
        $name = (string)($row['name'] ?? '');
        $slug = (string)($row['slug'] ?? '');
        $type = (string)($row['type'] ?? 'category');
        $description = (string)($row['description'] ?? '');
        $createdAt = (string)($row['created_at_display'] ?? ($row['created_at_raw'] ?? ''));

        $permalink = '';
        if ($slug !== '') {
            $permalink = (string)$urls->term($slug, $type);
        }

        return [
            'id'          => $id,
            'name'        => $name,
            'slug'        => $slug,
            'type'        => $type,
            'description' => $description,
            'permalink'   => $permalink,
            'created_at'  => $createdAt,
        ];
    }

    /**
     * @return array<string,array<int,string>>
     */
    private function validateTermInput(string $name, string $slug): array
    {
        $errors = [];

        if ($name === '') {
            $errors['name'][] = 'Název je povinný.';
        }

        if ($slug !== '' && preg_match('/\s/', $slug)) {
            $errors['slug'][] = 'Slug nesmí obsahovat mezery.';
        }

        return $errors;
    }

    /**
     * @return array<string,mixed>
     */
    private function safeDecodeValidationErrors(string $message): array
    {
        $decoded = json_decode($message, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return ['form' => [$message]];
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
}
