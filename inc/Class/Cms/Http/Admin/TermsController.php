<?php
declare(strict_types=1);

namespace Cms\Http\Admin;

use Cms\View\ViewEngine;
use Cms\Auth\AuthService;
use Cms\Domain\Repositories\TermsRepository;
use Cms\Domain\Services\TermsService;
use Core\Database\Init as DB;
use Cms\Utils\Slugger;

final class TermsController
{
    private ViewEngine $view;
    private AuthService $auth;

    public function __construct(string $baseViewsPath)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $this->view = new ViewEngine($baseViewsPath);
        $this->auth = new AuthService();
    }

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
    private function nav(): array
    {
        return [
            ['key'=>'dashboard','label'=>'Dashboard','href'=>'admin.php?r=dashboard','active'=>false],
            ['key'=>'posts','label'=>'Příspěvky','href'=>'admin.php?r=posts','active'=>false],
            ['key'=>'media','label'=>'Média','href'=>'admin.php?r=media','active'=>false],
            ['key'=>'terms','label'=>'Termy','href'=>'admin.php?r=terms','active'=>true],
            ['key'=>'comments','label'=>'Komentáře','href'=>'admin.php?r=comments','active'=>false],
            ['key'=>'users','label'=>'Uživatelé','href'=>'admin.php?r=users','active'=>false],
            ['key'=>'settings','label'=>'Nastavení','href'=>'admin.php?r=settings','active'=>false],
        ];
    }
    private function token(): string
    {
        if (empty($_SESSION['csrf_admin'])) $_SESSION['csrf_admin'] = bin2hex(random_bytes(16));
        return $_SESSION['csrf_admin'];
    }
    private function assertCsrf(): void
    {
        $in = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
        if (empty($_SESSION['csrf_admin']) || !hash_equals($_SESSION['csrf_admin'], (string)$in)) {
            http_response_code(419); echo 'CSRF token invalid'; exit;
        }
    }
    private function flash(string $type, string $msg): void
    {
        $_SESSION['_flash'] = ['type'=>$type,'msg'=>$msg];
    }

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

        $data = [
            'pageTitle'   => 'Termy',
            'nav'         => $this->nav(),
            'currentUser' => $this->auth->user(),
            'flash'       => $_SESSION['_flash'] ?? null,
            'filters'     => $filters,
            'items'       => $pag['items'] ?? [],
            'pagination'  => [
                'page'     => $pag['page'] ?? $page,
                'per_page' => $pag['per_page'] ?? $perPage,
                'total'    => $pag['total'] ?? 0,
                'pages'    => $pag['pages'] ?? 1,
            ],
            'csrf'        => $this->token(),
        ];
        unset($_SESSION['_flash']);

        $this->view->render('terms/index', $data);
    }

    /** Form create/edit */
    private function form(): void
    {
        $id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $row = null;
        if ($id > 0) {
            $row = (new TermsRepository())->find($id);
            if (!$row) { $this->flash('danger','Term nenalezen.'); header('Location: admin.php?r=terms'); exit; }
        }

        $data = [
            'pageTitle'   => $id ? 'Upravit term' : 'Nový term',
            'nav'         => $this->nav(),
            'currentUser' => $this->auth->user(),
            'flash'       => $_SESSION['_flash'] ?? null,
            'term'        => $row,
            'csrf'        => $this->token(),
        ];
        unset($_SESSION['_flash']);

        $this->view->render('terms/edit', $data);
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
            $this->flash('success','Term byl vytvořen.');
            header('Location: admin.php?r=terms&a=edit&id='.$id);
            exit;

        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
            header('Location: admin.php?r=terms&a=create');
            exit;
        }
    }

    /** Update existujícího termu */
    private function update(): void
    {
        $this->assertCsrf();
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { $this->flash('danger','Chybí ID.'); header('Location: admin.php?r=terms'); exit; }

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

            $this->flash('success', 'Změny byly uloženy.');
            header('Location: admin.php?r=terms&a=edit&id='.$id);
            exit;

        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
            header('Location: admin.php?r=terms&a=edit&id='.$id);
            exit;
        }
    }

    /** Smazání termu (odpojí z postů) */
    private function delete(): void
    {
        $this->assertCsrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { $this->flash('danger','Chybí ID.'); header('Location: admin.php?r=terms'); exit; }

        // Odpoj vazby a smaž term
        DB::query()->table('post_terms')->delete()->where('term_id','=',$id)->execute();
        DB::query()->table('terms')->delete()->where('id','=',$id)->execute();

        $this->flash('success','Term byl odstraněn.');
        header('Location: admin.php?r=terms');
        exit;
    }
}
