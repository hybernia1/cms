<?php
declare(strict_types=1);

namespace Cms\Http\Admin;

use Cms\Domain\Repositories\TermsRepository;
use Cms\Domain\Services\TermsService;
use Core\Database\Init as DB;
use Cms\Settings\CmsSettings;
use Cms\Utils\Slugger;
use Cms\Utils\AdminNavigation;
use Cms\Utils\DateTimeFactory;
use Cms\Utils\LinkGenerator;

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

        $q = DB::query()->table('terms','t')
            ->select(['t.id','t.type','t.slug','t.name','t.description','t.created_at'])
            ->orderBy('t.created_at','DESC');

        $q->where('t.type','=', $filters['type']);
        if ($filters['q']   !== '') {
            $like = '%' . $filters['q'] . '%';
            $q->where(function($w) use ($like) {
                $w->whereLike('t.name', $like)->orWhere('t.slug','LIKE',$like);
            });
        }

        $pag = $q->paginate($page, $perPage);

        $pagination = $this->paginationData($pag, $page, $perPage);
        $buildUrl = $this->listingUrlBuilder([
            'r'    => 'terms',
            'type' => $type,
            'q'    => $filters['q'],
        ]);

        $settings = new CmsSettings();
        $items = [];
        foreach (($pag['items'] ?? []) as $row) {
            $created = DateTimeFactory::fromStorage(isset($row['created_at']) ? (string)$row['created_at'] : null);
            $row['created_at_raw'] = isset($row['created_at']) ? (string)$row['created_at'] : '';
            if ($created) {
                $row['created_at_display'] = $settings->formatDateTime($created);
            } else {
                $row['created_at_display'] = $row['created_at_raw'];
            }
            $items[] = $row;
        }

        $this->renderAdmin('terms/index', [
            'pageTitle'  => $this->typeConfig()[$type]['list'],
            'nav'        => AdminNavigation::build('terms:' . $type),
            'filters'    => $filters,
            'items'      => $items,
            'pagination' => $pagination,
            'type'       => $type,
            'types'      => $this->typeConfig(),
            'urls'       => new LinkGenerator(),
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
        try {
            $name = trim((string)($_POST['name'] ?? ''));
            $type = (string)($_POST['type'] ?? $this->requestedType());
            if (!array_key_exists($type, $this->typeConfig())) {
                $type = 'category';
            }
            $slug = trim((string)($_POST['slug'] ?? ''));
            $desc = (string)($_POST['description'] ?? '');

            if ($name === '') throw new \RuntimeException('Název je povinný.');

            if ($slug === '') $slug = Slugger::make($name);
            $repo = new TermsRepository();
            if ($repo->findBySlug($slug)) {
                $slug .= '-' . substr(bin2hex(random_bytes(2)),0,3);
            }

            $id = (new TermsService())->create($name, $type, $slug, $desc);
            $this->redirect('admin.php?r=terms&a=edit&id=' . $id . '&type=' . urlencode($type), 'success', 'Term byl vytvořen.');

        } catch (\Throwable $e) {
            $type = $this->requestedType();
            $this->redirect('admin.php?r=terms&a=create&type=' . urlencode($type), 'danger', $e->getMessage());
        }
    }

    /** Update existujícího termu */
    private function update(): void
    {
        $this->assertCsrf();
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $type = $this->requestedType();
            $this->redirect('admin.php?r=terms&type=' . urlencode($type), 'danger', 'Chybí ID.');
        }

        try {
            $repo = new TermsRepository();
            $row = $repo->find($id);
            if (!$row) throw new \RuntimeException('Term nenalezen.');

            $name = trim((string)($_POST['name'] ?? ''));
            $slug = trim((string)($_POST['slug'] ?? ''));
            $type = (string)($row['type'] ?? 'category');
            $desc = (string)($_POST['description'] ?? '');

            if ($name === '') throw new \RuntimeException('Název je povinný.');
            if ($slug === '') $slug = Slugger::make($name);

            // unikátnost slugu
            $exists = $repo->findBySlug($slug);
            if ($exists && (int)$exists['id'] !== $id) {
                $slug .= '-' . substr(bin2hex(random_bytes(2)),0,3);
            }

            DB::query()->table('terms')->update([
                'name'        => $name,
                'slug'        => $slug,
                'type'        => $type,
                'description' => $desc,
                'created_at'  => $row['created_at'],
            ])->where('id','=',$id)->execute();

            $this->redirect('admin.php?r=terms&a=edit&id=' . $id . '&type=' . urlencode($type), 'success', 'Změny byly uloženy.');

        } catch (\Throwable $e) {
            $type = $this->requestedType();
            $this->redirect('admin.php?r=terms&a=edit&id=' . $id . '&type=' . urlencode($type), 'danger', $e->getMessage());
        }
    }

    /** Smazání termu (odpojí z postů) */
    private function delete(): void
    {
        $this->assertCsrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $type = $this->requestedType();
            $this->redirect('admin.php?r=terms&type=' . urlencode($type), 'danger', 'Chybí ID.');
        }

        // Odpoj vazby a smaž term
        DB::query()->table('post_terms')->delete()->where('term_id','=',$id)->execute();
        DB::query()->table('terms')->delete()->where('id','=',$id)->execute();

        $type = $this->requestedType();
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
            $this->redirect('admin.php?r=terms&type=' . urlencode($type), 'warning', 'Vyberte položky a požadovanou akci.');
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
            $this->redirect('admin.php?r=terms&type=' . urlencode($type), 'warning', 'Žádné platné položky pro hromadnou akci.');
        }

        try {
            if ($action === 'delete') {
                DB::query()->table('post_terms')->delete()->whereIn('term_id', $targetIds)->execute();
                DB::query()->table('terms')->delete()->whereIn('id', $targetIds)->execute();
            } else {
                $this->redirect('admin.php?r=terms&type=' . urlencode($type), 'warning', 'Neznámá hromadná akce.');
            }
        } catch (\Throwable $e) {
            $this->redirect('admin.php?r=terms&type=' . urlencode($type), 'danger', $e->getMessage());
        }

        $this->redirect(
            'admin.php?r=terms&type=' . urlencode($type),
            'success',
            'Hromadná akce dokončena (' . count($targetIds) . ')'
        );
    }
}
