<?php
declare(strict_types=1);

namespace Cms\Http\Admin;

use Cms\Domain\Repositories\TermsRepository;
use Cms\Domain\Services\TermsService;
use Core\Database\Init as DB;
use Cms\Utils\Slugger;
use Cms\Utils\AdminNavigation;

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

            case 'index':
            default:
                $this->index();
                return;
        }
    }

    // ------------- helpers -------------
    // ------------- actions -------------

    /** Přehled + filtry */
    private function index(): void
    {
        $filters = [
            'type' => (string)($_GET['type'] ?? ''), // category|tag|...
            'q'    => (string)($_GET['q'] ?? ''),
        ];
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;

        $q = DB::query()->table('terms','t')
            ->select(['t.id','t.type','t.slug','t.name','t.description','t.created_at'])
            ->orderBy('t.created_at','DESC');

        if ($filters['type'] !== '') $q->where('t.type','=', $filters['type']);
        if ($filters['q']   !== '') {
            $like = '%' . $filters['q'] . '%';
            $q->where(function($w) use ($like) {
                $w->whereLike('t.name', $like)->orWhere('t.slug','LIKE',$like);
            });
        }

        $pag = $q->paginate($page, $perPage);

        $this->renderAdmin('terms/index', [
            'pageTitle'  => 'Termy',
            'nav'        => AdminNavigation::build('terms'),
            'filters'    => $filters,
            'items'      => $pag['items'] ?? [],
            'pagination' => [
                'page'     => $pag['page'] ?? $page,
                'per_page' => $pag['per_page'] ?? $perPage,
                'total'    => $pag['total'] ?? 0,
                'pages'    => $pag['pages'] ?? 1,
            ],
        ]);
    }

    /** Form create/edit */
    private function form(): void
    {
        $id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $row = null;
        if ($id > 0) {
            $row = (new TermsRepository())->find($id);
            if (!$row) {
                $this->redirect('admin.php?r=terms', 'danger', 'Term nenalezen.');
            }
        }

        $this->renderAdmin('terms/edit', [
            'pageTitle' => $id ? 'Upravit term' : 'Nový term',
            'nav'       => AdminNavigation::build('terms'),
            'term'      => $row,
        ]);
    }

    /** Uložení nového termu */
    private function store(): void
    {
        $this->assertCsrf();
        try {
            $name = trim((string)($_POST['name'] ?? ''));
            $type = (string)($_POST['type'] ?? 'tag');
            $slug = trim((string)($_POST['slug'] ?? ''));
            $desc = (string)($_POST['description'] ?? '');

            if ($name === '') throw new \RuntimeException('Název je povinný.');

            if ($slug === '') $slug = Slugger::make($name);
            $repo = new TermsRepository();
            if ($repo->findBySlug($slug)) {
                $slug .= '-' . substr(bin2hex(random_bytes(2)),0,3);
            }

            $id = (new TermsService())->create($name, $type, $slug, $desc);
            $this->redirect('admin.php?r=terms&a=edit&id=' . $id, 'success', 'Term byl vytvořen.');

        } catch (\Throwable $e) {
            $this->redirect('admin.php?r=terms&a=create', 'danger', $e->getMessage());
        }
    }

    /** Update existujícího termu */
    private function update(): void
    {
        $this->assertCsrf();
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('admin.php?r=terms', 'danger', 'Chybí ID.');
        }

        try {
            $repo = new TermsRepository();
            $row = $repo->find($id);
            if (!$row) throw new \RuntimeException('Term nenalezen.');

            $name = trim((string)($_POST['name'] ?? ''));
            $slug = trim((string)($_POST['slug'] ?? ''));
            $type = (string)($_POST['type'] ?? $row['type']);
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

            $this->redirect('admin.php?r=terms&a=edit&id=' . $id, 'success', 'Změny byly uloženy.');

        } catch (\Throwable $e) {
            $this->redirect('admin.php?r=terms&a=edit&id=' . $id, 'danger', $e->getMessage());
        }
    }

    /** Smazání termu (odpojí z postů) */
    private function delete(): void
    {
        $this->assertCsrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('admin.php?r=terms', 'danger', 'Chybí ID.');
        }

        // Odpoj vazby a smaž term
        DB::query()->table('post_terms')->delete()->where('term_id','=',$id)->execute();
        DB::query()->table('terms')->delete()->where('id','=',$id)->execute();

        $this->redirect('admin.php?r=terms', 'success', 'Term byl odstraněn.');
    }
}
