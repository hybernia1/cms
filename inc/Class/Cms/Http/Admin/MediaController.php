<?php
declare(strict_types=1);

namespace Cms\Http\Admin;

use Core\Database\Init as DB;
use Cms\Domain\Services\MediaService;
use Cms\Settings\CmsSettings;
use Cms\Utils\AdminNavigation;
use Core\Files\PathResolver;

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
            case 'optimize':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->optimize(); return; }
                // fallthrough na index
            case 'library':
                $this->library();
                return;
            case 'upload-editor':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->uploadFromEditor(); return; }
                http_response_code(405);
                echo 'Method Not Allowed';
                return;
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
            ->select([
                'm.id','m.user_id','m.type','m.mime','m.url','m.rel_path','m.created_at','m.meta',
                'u.name AS author_name','u.email AS author_email'
            ])
            ->leftJoin('users u', 'u.id', '=', 'm.user_id')
            ->orderBy('m.created_at','DESC');

        if ($filters['type'] !== '') $q->where('m.type','=', $filters['type']);
        if ($filters['q']   !== '') {
            $like = '%' . $filters['q'] . '%';
            $q->where(function($w) use ($like) {
                $w->whereLike('m.url', $like)->orWhere('m.mime', 'LIKE', $like);
            });
        }

        $pag = $q->paginate($page, $perPage);

        $paths = $this->uploadPaths();
        $settings = new CmsSettings();
        $items = array_map(fn(array $row) => $this->prepareItem($row, $paths), $pag['items'] ?? []);

        $this->renderAdmin('media/index', [
            'pageTitle'  => 'Média',
            'nav'        => AdminNavigation::build('media'),
            'filters'    => $filters,
            'items'      => $items,
            'pagination' => [
                'page'     => $pag['page'] ?? $page,
                'per_page' => $pag['per_page'] ?? $perPage,
                'total'    => $pag['total'] ?? 0,
                'pages'    => $pag['pages'] ?? 1,
            ],
            'webpEnabled'=> $settings->webpEnabled(),
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

    private function uploadFromEditor(): void
    {
        $this->assertCsrf();

        header('Content-Type: application/json; charset=utf-8');

        try {
            $user = $this->auth->user();
            if (!$user) {
                throw new \RuntimeException('Nejste přihlášeni.');
            }

            if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
                throw new \RuntimeException('Nebyl vybrán žádný soubor.');
            }

            if ((int)$_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
                throw new \RuntimeException('Nebyl vybrán žádný soubor.');
            }

            if ((int)$_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('Soubor se nepodařilo nahrát.');
            }

            $postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;

            $svc   = new MediaService();
            $paths = $this->uploadPaths();

            $created = $svc->uploadAndCreate(
                $_FILES['file'],
                (int)$user['id'],
                $paths,
                'posts',
                $postId > 0 ? $postId : null
            );

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'item'    => $created,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        exit;
    }

    private function optimize(): void
    {
        $this->assertCsrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('admin.php?r=media', 'danger', 'Chybí ID.');
        }

        try {
            $svc = new MediaService();
            $created = $svc->optimizeWebp($id, $this->uploadPaths());
            if ($created) {
                $this->redirect('admin.php?r=media', 'success', 'WebP varianta byla vytvořena.');
            }
            $this->redirect('admin.php?r=media', 'info', 'WebP varianta již existuje.');
        } catch (\Throwable $e) {
            $this->redirect('admin.php?r=media', 'danger', $e->getMessage());
        }
    }

    private function library(): void
    {
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 60)));
        $type = trim((string)($_GET['type'] ?? ''));

        $q = DB::query()->table('media','m')
            ->select(['m.id','m.url','m.mime','m.type','m.created_at'])
            ->orderBy('m.created_at','DESC')
            ->limit($limit);

        if ($type !== '') {
            $q->where('m.type','=', $type);
        }

        $rows = $q->get();
        $items = [];
        foreach ($rows as $row) {
            $url = (string)($row['url'] ?? '');
            $path = $url !== '' ? parse_url($url, PHP_URL_PATH) : '';
            $basename = $path ? basename((string)$path) : '';
            if ($basename === '') {
                $basename = 'ID ' . (int)($row['id'] ?? 0);
            }
            $items[] = [
                'id'   => (int)($row['id'] ?? 0),
                'url'  => $url,
                'mime' => (string)($row['mime'] ?? ''),
                'type' => (string)($row['type'] ?? ''),
                'name' => $basename,
            ];
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function prepareItem(array $row, PathResolver $paths): array
    {
        $meta = $this->decodeMeta($row['meta'] ?? null);
        $webpRel = isset($meta['webp']) && is_string($meta['webp']) && $meta['webp'] !== '' ? (string)$meta['webp'] : null;
        $webpUrl = null;
        if ($webpRel !== null) {
            try {
                $webpUrl = $paths->publicUrl($webpRel);
            } catch (\Throwable) {
                $webpUrl = null;
            }
        }

        $displayUrl = $webpUrl ?? (string)($row['url'] ?? '');
        $relPath = (string)($row['rel_path'] ?? '');

        $sizeBytes = null;
        if ($relPath !== '') {
            try {
                $abs = $paths->absoluteFromRelative($relPath);
                if (is_file($abs)) {
                    $size = @filesize($abs);
                    if ($size !== false) {
                        $sizeBytes = (int)$size;
                    }
                    if ((!isset($meta['w']) || !isset($meta['h'])) && str_starts_with((string)($row['mime'] ?? ''), 'image/')) {
                        $dim = @getimagesize($abs);
                        if ($dim) {
                            $meta['w'] = (int)$dim[0];
                            $meta['h'] = (int)$dim[1];
                        }
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        $createdAt = (string)($row['created_at'] ?? '');
        $createdDisplay = '';
        $createdIso = '';
        if ($createdAt !== '') {
            try {
                $dt = new \DateTimeImmutable($createdAt);
                $createdDisplay = $dt->format('Y-m-d H:i');
                $createdIso = $dt->format(DATE_ATOM);
            } catch (\Throwable) {
                $createdDisplay = '';
                $createdIso = '';
            }
        }

        return [
            'id'              => (int)($row['id'] ?? 0),
            'user_id'         => (int)($row['user_id'] ?? 0),
            'type'            => (string)($row['type'] ?? ''),
            'mime'            => (string)($row['mime'] ?? ''),
            'url'             => (string)($row['url'] ?? ''),
            'rel_path'        => $relPath,
            'created_at'      => $createdAt,
            'created_display' => $createdDisplay,
            'created_iso'     => $createdIso,
            'author_name'     => (string)($row['author_name'] ?? ''),
            'author_email'    => (string)($row['author_email'] ?? ''),
            'meta'            => $meta,
            'webp_url'        => $webpUrl,
            'display_url'     => $displayUrl,
            'has_webp'        => $webpUrl !== null,
            'size_bytes'      => $sizeBytes,
            'size_human'      => $sizeBytes !== null ? $this->formatBytes($sizeBytes) : null,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeMeta(mixed $raw): array
    {
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B','KB','MB','GB','TB'];
        $i = 0;
        $value = (float)$bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        if ($i === 0) {
            return sprintf('%d %s', (int)$value, $units[$i]);
        }

        return sprintf('%.1f %s', $value, $units[$i]);
    }
}
