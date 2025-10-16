<?php
declare(strict_types=1);

namespace Cms\Http\Admin;

use Core\Database\Init as DB;
use Cms\Domain\Services\MediaService;
use Cms\Utils\AdminNavigation;

final class MediaController extends BaseAdminController
{
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

        $this->renderAdmin('media/index', [
            'pageTitle'  => 'Média',
            'nav'        => AdminNavigation::build('media'),
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
            $paths = $this->uploadPaths();

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

            if ($uploadedCount === 0) {
                throw new \RuntimeException('Nic se nenahrálo.');
            }

            $this->redirect('admin.php?r=media', 'success', "Nahráno souborů: {$uploadedCount}.");

        } catch (\Throwable $e) {
            $this->redirect('admin.php?r=media', 'danger', $e->getMessage());
        }
    }

    private function delete(): void
    {
        $this->assertCsrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('admin.php?r=media', 'danger', 'Chybí ID.');
        }

        // Smažeme pouze DB záznam; fyzické soubory můžeš mazat také (opatrně), ukážu jednoduchou variantu:
        $row = DB::query()->table('media')->select(['id','rel_path','url'])->where('id','=',$id)->first();

        DB::query()->table('media')->delete()->where('id','=',$id)->execute();
        DB::query()->table('post_media')->delete()->where('media_id','=',$id)->execute();

        // pokus o smazání fyzického souboru jen pokud máme bezpečnou relativní cestu
        if ($row && !empty($row['rel_path'])) {
            $abs = dirname(__DIR__, 5) . '/uploads/' . ltrim((string)$row['rel_path'], '/\\');
            if (is_file($abs)) @unlink($abs);
        }

        $this->redirect('admin.php?r=media', 'success', 'Soubor odstraněn.');
    }
}
