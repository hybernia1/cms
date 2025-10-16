<?php
declare(strict_types=1);

namespace Cms\Http\Admin;

use Cms\View\ViewEngine;
use Cms\Auth\AuthService;
use Core\Database\Init as DB;
use Cms\Domain\Services\MediaService;
use Core\Files\PathResolver;

final class MediaController
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
            case 'upload':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->upload(); return; }
                // fallthrough na index
            case 'delete':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->delete(); return; }
                // fallthrough na index
            case 'index':
            default:
                $this->index();
                return;
        }
    }

    // ---------------- helpers ----------------
    private function nav(): array
    {
        return [
            ['key'=>'dashboard','label'=>'Dashboard','href'=>'admin.php?r=dashboard','active'=>false],
            ['key'=>'posts','label'=>'Příspěvky','href'=>'admin.php?r=posts','active'=>false],
            ['key'=>'media','label'=>'Média','href'=>'admin.php?r=media','active'=>true],
            ['key'=>'terms','label'=>'Termy','href'=>'admin.php?r=terms','active'=>false],
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
    private function paths(): PathResolver
    {
        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $webBase = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        $baseUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($webBase === '' ? '' : $webBase) . '/uploads';
        return new PathResolver(baseDir: dirname(__DIR__, 5) . '/uploads', baseUrl: $baseUrl);
    }

    // ---------------- actions ----------------
    private function index(): void
    {
        $filters = [
            'type' => (string)($_GET['type'] ?? ''),     // image|file|<empty>
            'q'    => (string)($_GET['q'] ?? ''),        // hledání v url/mime
        ];
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 30;

        $q = DB::query()->table('media','m')
            ->select(['m.id','m.user_id','m.type','m.mime','m.url','m.rel_path','m.created_at'])
            ->orderBy('m.created_at','DESC');

        if ($filters['type'] !== '') $q->where('m.type','=', $filters['type']);
        if ($filters['q']   !== '') {
            $like = '%' . $filters['q'] . '%';
            $q->where(function($w) use ($like) {
                $w->whereLike('m.url', $like)->orWhere('m.mime', 'LIKE', $like);
            });
        }

        $pag = $q->paginate($page, $perPage);

        $data = [
            'pageTitle'   => 'Média',
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

        $this->view->render('media/index', $data);
    }

    private function upload(): void
    {
        $this->assertCsrf();
        try {
            $user = $this->auth->user();
            if (!$user) throw new \RuntimeException('Nejste přihlášeni.');

            if (empty($_FILES['files'])) {
                throw new \RuntimeException('Nebyl vybrán žádný soubor.');
            }

            $svc   = new MediaService();
            $paths = $this->paths();

            // multiple upload podpora
            $uploadedCount = 0;
            $files = $_FILES['files'];
            if (is_array($files['name'])) {
                $count = count($files['name']);
                for ($i=0; $i<$count; $i++) {
                    if ((int)$files['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                    $fileArr = [
                        'name'     => $files['name'][$i],
                        'type'     => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error'    => $files['error'][$i],
                        'size'     => $files['size'][$i],
                    ];
                    $svc->uploadAndCreate($fileArr, (int)$user['id'], $paths, 'media');
                    $uploadedCount++;
                }
            } else {
                if ((int)$files['error'] !== UPLOAD_ERR_NO_FILE) {
                    $svc->uploadAndCreate($files, (int)$user['id'], $paths, 'media');
                    $uploadedCount++;
                }
            }

            if ($uploadedCount === 0) throw new \RuntimeException('Nic se nenahrálo.');
            $this->flash('success', "Nahráno souborů: {$uploadedCount}.");
            header('Location: admin.php?r=media');
            exit;

        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
            header('Location: admin.php?r=media');
            exit;
        }
    }

    private function delete(): void
    {
        $this->assertCsrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { $this->flash('danger','Chybí ID.'); header('Location: admin.php?r=media'); exit; }

        // Smažeme pouze DB záznam; fyzické soubory můžeš mazat také (opatrně), ukážu jednoduchou variantu:
        $row = DB::query()->table('media')->select(['id','rel_path','url'])->where('id','=',$id)->first();

        DB::query()->table('media')->delete()->where('id','=',$id)->execute();
        DB::query()->table('post_media')->delete()->where('media_id','=',$id)->execute();

        // pokus o smazání fyzického souboru jen pokud máme bezpečnou relativní cestu
        if ($row && !empty($row['rel_path'])) {
            $abs = dirname(__DIR__, 5) . '/uploads/' . ltrim((string)$row['rel_path'], '/\\');
            if (is_file($abs)) @unlink($abs);
        }

        $this->flash('success','Soubor odstraněn.');
        header('Location: admin.php?r=media');
        exit;
    }
}
