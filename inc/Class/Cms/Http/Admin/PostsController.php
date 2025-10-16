<?php
declare(strict_types=1);

namespace Cms\Http\Admin;

use Cms\View\ViewEngine;
use Cms\Auth\AuthService;
use Cms\Domain\Repositories\PostsRepository;
use Cms\Domain\Services\PostsService;
use Cms\Domain\Services\MediaService;
use Cms\Utils\AdminNavigation;
use Core\Files\PathResolver;
use Core\Database\Init as DB;

final class PostsController
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
            'post'    => ['nav' => 'Příspěvky', 'list' => 'Příspěvky', 'create' => 'Nový příspěvek', 'edit' => 'Upravit příspěvek', 'label' => 'Příspěvek'],
            'page'    => ['nav' => 'Stránky', 'list' => 'Stránky', 'create' => 'Nová stránka', 'edit' => 'Upravit stránku', 'label' => 'Stránka'],
            'product' => ['nav' => 'Produkty', 'list' => 'Produkty', 'create' => 'Nový produkt', 'edit' => 'Upravit produkt', 'label' => 'Produkt'],
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

    private function token(): string
    {
        if (empty($_SESSION['csrf_admin'])) {
            $_SESSION['csrf_admin'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf_admin'];
    }
    private function assertCsrf(): void
    {
        $in = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
        if (empty($_SESSION['csrf_admin']) || !hash_equals($_SESSION['csrf_admin'], (string)$in)) {
            http_response_code(419);
            echo 'CSRF token invalid';
            exit;
        }
    }
    private function flash(string $type, string $msg): void
    {
        $_SESSION['_flash'] = ['type'=>$type,'msg'=>$msg];
    }
    private function basePaths(): PathResolver
    {
        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $webBase = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        $baseUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($webBase === '' ? '' : $webBase) . '/uploads';
        return new PathResolver(baseDir: dirname(__DIR__, 5) . '/uploads', baseUrl: $baseUrl);
    }

    /** Načte seznam termů rozdělený podle typu + aktuálně vybrané pro daný post. */
    private function termsData(?int $postId): array
    {
        // všecky termy
        $all = DB::query()->table('terms','t')
            ->select(['t.id','t.name','t.slug','t.type'])
            ->orderBy('t.type','ASC')->orderBy('t.name','ASC')->get();

        $byType = ['category'=>[], 'tag'=>[]];
        foreach ($all as $t) {
            $type = (string)$t['type'];
            $byType[$type][] = $t;
        }

        // předvybrané
        $selected = ['category'=>[], 'tag'=>[]];
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

    /** Uloží vazby post↔terms (přepíše existující). */
    private function syncTerms(int $postId, array $categoryIds, array $tagIds): void
    {
        // očisti na int a unikáty
        $cat = array_values(array_unique(array_map('intval', $categoryIds)));
        $tag = array_values(array_unique(array_map('intval', $tagIds)));

        DB::query()->table('post_terms')->delete()->where('post_id','=', $postId)->execute();

        $ins = DB::query()->table('post_terms')->insert(['post_id','term_id']);
        foreach (array_merge($cat, $tag) as $tid) {
            if ($tid > 0) { $ins->values([$postId, $tid]); }
        }
        $ins->execute();
    }

    // ---------------- Actions ----------------

    private function index(): void
    {
        $repo = new PostsRepository();

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

        $data = [
            'pageTitle'   => $this->typeConfig()[$type]['list'],
            'nav'         => AdminNavigation::build('posts:' . $type),
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
            'type'        => $type,
            'types'       => $this->typeConfig(),
        ];
        unset($_SESSION['_flash']);

        $this->view->render('posts/index', $data);
    }

    private function form(): void
    {
        $id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $row = null;
        $type = $this->requestedType();
        if ($id > 0) {
            $row = (new PostsRepository())->find($id);
            if (!$row) { $this->flash('danger','Příspěvek nebyl nalezen.'); header('Location: ' . $this->listUrl($type)); exit; }
            $rowType = (string)($row['type'] ?? 'post');
            if (array_key_exists($rowType, $this->typeConfig())) {
                $type = $rowType;
            }
        }

        $terms = $this->termsData($id ?: null);

        $data = [
            'pageTitle'   => $this->typeConfig()[$type][$id ? 'edit' : 'create'],
            'nav'         => AdminNavigation::build('posts:' . $type),
            'currentUser' => $this->auth->user(),
            'flash'       => $_SESSION['_flash'] ?? null,
            'post'        => $row,
            'csrf'        => $this->token(),
            'terms'       => $terms['byType'],
            'selected'    => $terms['selected'],
            'type'        => $type,
            'types'       => $this->typeConfig(),
        ];
        unset($_SESSION['_flash']);

        $this->view->render('posts/edit', $data);
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

            // terms z formuláře
            $catIds = isset($_POST['categories']) ? (array)$_POST['categories'] : [];
            $tagIds = isset($_POST['tags']) ? (array)$_POST['tags'] : [];

            $thumbId = null;
            if (!empty($_FILES['thumbnail']) && (int)$_FILES['thumbnail']['error'] !== UPLOAD_ERR_NO_FILE) {
                $mediaSvc = new MediaService();
                $up = $mediaSvc->uploadAndCreate($_FILES['thumbnail'], (int)$user['id'], $this->basePaths(), 'posts');
                $thumbId = (int)$up['id'];
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

            // ulož vazby termů
            $this->syncTerms($postId, $catIds, $tagIds);

            $this->flash('success', 'Příspěvek byl vytvořen.');
            header('Location: admin.php?r=posts&a=edit&id='.(int)$postId.'&type='.$type);
            exit;

        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
            $type = $this->requestedType();
            header('Location: admin.php?r=posts&a=create&type='.$type);
            exit;
        }
    }

    private function update(): void
    {
        $this->assertCsrf();
        $id = (int)($_GET['id'] ?? 0);
        $type = $this->requestedType();
        if ($id <= 0) { $this->flash('danger','Chybí ID.'); header('Location: '.$this->listUrl($type)); exit; }

        $post = (new PostsRepository())->find($id);
        if (!$post) { $this->flash('danger','Příspěvek nebyl nalezen.'); header('Location: '.$this->listUrl($type)); exit; }
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
                'comments_allowed' => isset($_POST['comments_allowed']) ? 1 : 0,
            ];
            if (isset($_POST['slug']) && trim((string)$_POST['slug']) !== '') {
                $upd['slug'] = (string)$_POST['slug'];
            }

            // terms z formuláře
            $catIds = isset($_POST['categories']) ? (array)$_POST['categories'] : [];
            $tagIds = isset($_POST['tags']) ? (array)$_POST['tags'] : [];

            // případný nový thumbnail
            if (!empty($_FILES['thumbnail']) && (int)$_FILES['thumbnail']['error'] !== UPLOAD_ERR_NO_FILE) {
                $mediaSvc = new MediaService();
                $up = $mediaSvc->uploadAndCreate($_FILES['thumbnail'], (int)$user['id'], $this->basePaths(), 'posts');
                $upd['thumbnail_id'] = (int)$up['id'];
            }

            (new PostsService())->update($id, $upd);

            // ulož vazby termů (přepíše existující)
            $this->syncTerms($id, $catIds, $tagIds);

            $this->flash('success', 'Změny byly uloženy.');
            header('Location: admin.php?r=posts&a=edit&id='.$id.'&type='.$type);
            exit;

        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
            header('Location: admin.php?r=posts&a=edit&id='.$id.'&type='.$type);
            exit;
        }
    }

    private function delete(): void
    {
        $this->assertCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $type = $this->requestedType();
        if ($id <= 0) { $this->flash('danger','Chybí ID.'); header('Location: '.$this->listUrl($type)); exit; }

        $post = (new PostsRepository())->find($id);
        if ($post) {
            $rowType = (string)($post['type'] ?? '');
            if (array_key_exists($rowType, $this->typeConfig())) {
                $type = $rowType;
            }
        }

        // smaž vazby i post
        DB::query()->table('post_terms')->delete()->where('post_id','=', $id)->execute();
        (new PostsRepository())->delete($id);

        $this->flash('success', 'Příspěvek byl odstraněn.');
        header('Location: '.$this->listUrl($type));
        exit;
    }

    private function toggleStatus(): void
    {
        $this->assertCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $type = $this->requestedType();
        if ($id <= 0) { $this->flash('danger','Chybí ID.'); header('Location: '.$this->listUrl($type)); exit; }

        $post = (new PostsRepository())->find($id);
        if (!$post) { $this->flash('danger','Příspěvek nenalezen.'); header('Location: '.$this->listUrl($type)); exit; }
        $rowType = (string)($post['type'] ?? 'post');
        if (array_key_exists($rowType, $this->typeConfig())) {
            $type = $rowType;
        }

        $new = ((string)$post['status'] === 'publish') ? 'draft' : 'publish';
        (new PostsService())->update($id, ['status'=>$new]);

        $this->flash('success', $new === 'publish' ? 'Publikováno.' : 'Přepnuto na draft.');
        header('Location: '.$this->listUrl($type));
        exit;
    }
}
