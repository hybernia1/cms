<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Controllers;

use Core\Database\Init as DB;
use Cms\Admin\Domain\Services\MediaService;
use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Utils\AdminNavigation;
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
    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    private function mediaViewData(array $query = []): array
    {
        $source = $query === [] ? $_GET : $query;

        $filters = [
            'type' => isset($source['type']) ? (string)$source['type'] : '',
            'q'    => isset($source['q']) ? (string)$source['q'] : '',
        ];
        $page = max(1, (int)($source['page'] ?? 1));
        $perPage = 30;

        $q = DB::query()->table('media', 'm')
            ->select([
                'm.id','m.user_id','m.type','m.mime','m.url','m.rel_path','m.created_at','m.meta',
                'u.name AS author_name','u.email AS author_email'
            ])
            ->leftJoin('users u', 'u.id', '=', 'm.user_id')
            ->orderBy('m.created_at','DESC');

        if ($filters['type'] !== '') {
            $q->where('m.type','=', $filters['type']);
        }
        if ($filters['q'] !== '') {
            $like = '%' . $filters['q'] . '%';
            $q->where(function($w) use ($like) {
                $w->whereLike('m.url', $like)->orWhere('m.mime', 'LIKE', $like);
            });
        }

        $pag = $q->paginate($page, $perPage);

        $paths = $this->uploadPaths();
        $settings = new CmsSettings();
        $items = array_map(fn(array $row) => $this->prepareItem($row, $paths), $pag['items'] ?? []);
        $mediaIds = array_map(static fn(array $item): int => (int)($item['id'] ?? 0), $items);
        $references = $this->mediaReferences($mediaIds);
        $items = array_map(static function (array $item) use ($references): array {
            $id = (int)($item['id'] ?? 0);
            $item['references'] = $references[$id] ?? ['thumbnails' => [], 'content' => []];
            return $item;
        }, $items);

        return [
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
        ];
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $extra
     * @return never
     */
    private function respondWithMediaListing(?string $flashType, ?string $flashMessage, array $query = [], array $extra = []): never
    {
        if ($flashType !== null && $flashMessage !== null) {
            $this->flash($flashType, $flashMessage);
        }

        $data = $this->mediaViewData($query);
        $captured = $this->captureView('media/index', $data);

        $payload = array_merge([
            'success' => true,
            'html'    => $captured['html'],
        ], $extra);

        if ($flashMessage !== null) {
            $payload['message'] = $flashMessage;
        }

        if (is_array($captured['flash'])) {
            $payload['flash'] = $captured['flash'];
        }

        $this->respondJson($payload);
    }

    // ---------------- actions ----------------
    private function index(): void
    {
        $data = $this->mediaViewData($_GET);

        if ($this->isAjax()) {
            $captured = $this->captureView('media/index', $data);
            $payload = [
                'success' => true,
                'html'    => $captured['html'],
            ];
            if (is_array($captured['flash'])) {
                $payload['flash'] = $captured['flash'];
            }

            $this->respondJson($payload);
        }

        $this->renderAdmin('media/index', $data);
    }

    private function upload(): void
    {
        $this->assertCsrf();
        try {
            $user = $this->auth->user();
            if (!$user) {
                throw new \RuntimeException('Nejste přihlášeni.');
            }

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
                for ($i = 0; $i < $count; $i++) {
                    if ((int)$files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
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
            } elseif ((int)$files['error'] !== UPLOAD_ERR_NO_FILE) {
                $svc->uploadAndCreate($files, (int)$user['id'], $paths, 'media');
                $uploadedCount++;
            }

            if ($uploadedCount === 0) {
                throw new \RuntimeException('Nic se nenahrálo.');
            }

            $message = "Nahráno souborů: {$uploadedCount}.";

            if ($this->isAjax()) {
                $this->respondWithMediaListing('success', $message, $_GET, ['uploaded' => $uploadedCount]);
            }

            $this->redirect('admin.php?r=media', 'success', $message);
        } catch (\Throwable $e) {
            $message = trim((string)$e->getMessage()) ?: 'Nahrání selhalo.';
            if ($this->isAjax()) {
                $this->jsonError($message, 400);
            }
            $this->redirect('admin.php?r=media', 'danger', $message);
        }
    }

    private function delete(): void
    {
        $this->assertCsrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            if ($this->isAjax()) {
                $this->jsonError('Chybí ID.', 422);
            }
            $this->redirect('admin.php?r=media', 'danger', 'Chybí ID.');
        }

        try {
            $row = DB::query()->table('media')->select(['id','rel_path','url'])->where('id','=',$id)->first();

            DB::query()->table('media')->delete()->where('id','=',$id)->execute();
            DB::query()->table('post_media')->delete()->where('media_id','=',$id)->execute();

            if ($row && !empty($row['rel_path'])) {
                $abs = dirname(__DIR__, 5) . '/uploads/' . ltrim((string)$row['rel_path'], '/\\');
                if (is_file($abs)) {
                    @unlink($abs);
                }
            }

            $message = 'Soubor odstraněn.';

            if ($this->isAjax()) {
                $this->respondWithMediaListing('success', $message, $_GET, ['deletedId' => $id]);
            }

            $this->redirect('admin.php?r=media', 'success', $message);
        } catch (\Throwable $e) {
            $message = trim((string)$e->getMessage()) ?: 'Smazání souboru selhalo.';
            if ($this->isAjax()) {
                $this->jsonError($message, 500);
            }
            $this->redirect('admin.php?r=media', 'danger', $message);
        }
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
            if ($this->isAjax()) {
                $this->jsonError('Chybí ID.', 422);
            }
            $this->redirect('admin.php?r=media', 'danger', 'Chybí ID.');
        }

        try {
            $svc = new MediaService();
            $created = $svc->optimizeWebp($id, $this->uploadPaths());

            $message = $created ? 'WebP varianta byla vytvořena.' : 'WebP varianta již existuje.';
            $flashType = $created ? 'success' : 'info';

            if ($this->isAjax()) {
                $this->respondWithMediaListing($flashType, $message, $_GET, [
                    'optimizedId' => $id,
                    'webpCreated' => $created,
                ]);
            }

            $this->redirect('admin.php?r=media', $flashType, $message);
        } catch (\Throwable $e) {
            $message = trim((string)$e->getMessage()) ?: 'Optimalizace selhala.';
            if ($this->isAjax()) {
                $this->jsonError($message, 500);
            }
            $this->redirect('admin.php?r=media', 'danger', $message);
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

    /**
     * @param array<int> $mediaIds
     * @return array<int,array{thumbnails:array<int,array<string,mixed>>,content:array<int,array<string,mixed>>}>
     */
    private function mediaReferences(array $mediaIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $mediaIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $map = [];
        foreach ($ids as $id) {
            $map[$id] = ['thumbnails' => [], 'content' => []];
        }

        $thumbRows = DB::query()->table('posts')
            ->select(['id','title','slug','type','status','thumbnail_id'])
            ->whereIn('thumbnail_id', $ids)
            ->get();

        foreach ($thumbRows as $row) {
            $mediaId = (int)($row['thumbnail_id'] ?? 0);
            $postId = (int)($row['id'] ?? 0);
            if ($mediaId <= 0 || $postId <= 0 || !isset($map[$mediaId])) {
                continue;
            }
            $map[$mediaId]['thumbnails'][$postId] = $this->mapPostReference($row, 'thumbnail');
        }

        $contentRows = DB::query()->table('post_media', 'pm')
            ->select(['pm.media_id','pm.role','p.id','p.title','p.slug','p.type','p.status'])
            ->join('posts p', 'pm.post_id', '=', 'p.id')
            ->whereIn('pm.media_id', $ids)
            ->get();

        foreach ($contentRows as $row) {
            $mediaId = (int)($row['media_id'] ?? 0);
            $postId = (int)($row['id'] ?? 0);
            if ($mediaId <= 0 || $postId <= 0 || !isset($map[$mediaId])) {
                continue;
            }
            $role = (string)($row['role'] ?? 'attachment');
            $key = $postId . '|' . $role;
            $map[$mediaId]['content'][$key] = $this->mapPostReference($row, $role);
        }

        foreach ($map as &$groups) {
            $groups['thumbnails'] = array_values($groups['thumbnails']);
            $groups['content'] = array_values($groups['content']);
        }
        unset($groups);

        return $map;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function mapPostReference(array $row, string $context): array
    {
        $postId = (int)($row['id'] ?? 0);
        return [
            'id' => $postId,
            'title' => (string)($row['title'] ?? ''),
            'slug' => (string)($row['slug'] ?? ''),
            'status' => (string)($row['status'] ?? ''),
            'status_label' => $this->postStatusLabel((string)($row['status'] ?? '')),
            'type' => (string)($row['type'] ?? ''),
            'type_label' => $this->postTypeLabel((string)($row['type'] ?? '')),
            'edit_url' => 'admin.php?r=posts&a=edit&id=' . $postId,
            'role' => $context,
            'role_label' => $context === 'attachment' ? '' : $this->mediaRoleLabel($context),
        ];
    }

    private function postStatusLabel(string $status): string
    {
        return match ($status) {
            'publish' => 'Publikováno',
            'draft' => 'Koncept',
            'future' => 'Naplánováno',
            default => ucfirst($status),
        };
    }

    private function postTypeLabel(string $type): string
    {
        return match ($type) {
            'page' => 'Stránka',
            'post' => 'Příspěvek',
            default => ucfirst($type),
        };
    }

    private function mediaRoleLabel(string $role): string
    {
        return match ($role) {
            'gallery' => 'Galerie',
            'hero' => 'Hero sekce',
            default => ucfirst($role),
        };
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
