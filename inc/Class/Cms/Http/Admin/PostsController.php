<?php
declare(strict_types=1);

namespace Cms\Http\Admin;

use Cms\Domain\Repositories\PostsRepository;
use Cms\Domain\Repositories\TermsRepository;
use Cms\Domain\Services\PostsService;
use Cms\Domain\Services\MediaService;
use Cms\Domain\Services\TermsService;
use Cms\Settings\CmsSettings;
use Cms\Utils\AdminNavigation;
use Cms\Utils\DateTimeFactory;
use Cms\Utils\LinkGenerator;
use Core\Database\Init as DB;

final class PostsController extends BaseAdminController
{
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
            'post' => [
                'nav'    => 'Příspěvky',
                'list'   => 'Příspěvky',
                'create' => 'Nový příspěvek',
                'edit'   => 'Upravit příspěvek',
                'label'  => 'Příspěvek',
            ],
            'page' => [
                'nav'    => 'Stránky',
                'list'   => 'Stránky',
                'create' => 'Nová stránka',
                'edit'   => 'Upravit stránku',
                'label'  => 'Stránka',
            ],
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

    /** Načte seznam termů rozdělený podle typu + aktuálně vybrané pro daný post. */
    private function termsData(?int $postId, string $type): array
    {
        $byType = ['category'=>[], 'tag'=>[]];
        $selected = ['category'=>[], 'tag'=>[]];

        if ($type !== 'post') {
            return ['byType'=>$byType, 'selected'=>$selected];
        }

        // všecky termy
        $all = DB::query()->table('terms','t')
            ->select(['t.id','t.name','t.slug','t.type'])
            ->orderBy('t.type','ASC')->orderBy('t.name','ASC')->get();

        foreach ($all as $t) {
            $termType = (string)$t['type'];
            $byType[$termType][] = $t;
        }

        // předvybrané
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

    /** Uloží vazby post↔terms (přepíše existující). */
    private function syncTerms(int $postId, array $categoryIds, array $tagIds): void
    {
        // očisti na int a unikáty
        $cat = array_values(array_unique(array_map('intval', $categoryIds)));
        $tag = array_values(array_unique(array_map('intval', $tagIds)));

        DB::query()->table('post_terms')->delete()->where('post_id','=', $postId)->execute();

        $ins = DB::query()->table('post_terms')->insert(['post_id','term_id']);
        $hasRows = false;
        foreach (array_merge($cat, $tag) as $tid) {
            if ($tid > 0) {
                $ins->values([$postId, $tid]);
                $hasRows = true;
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
        $statusCounts = $repo->countByStatus($type);

        $settings = new CmsSettings();
        $items = [];
        foreach (($pag['items'] ?? []) as $row) {
            $created = DateTimeFactory::fromStorage(isset($row['created_at']) ? (string)$row['created_at'] : null);
            $row['created_at_raw'] = isset($row['created_at']) ? (string)$row['created_at'] : '';
            if ($created) {
                $row['created_at_display'] = $settings->formatDateTime($created);
                $row['created_at_iso'] = $created->format(\DateTimeInterface::ATOM);
            } else {
                $row['created_at_display'] = $row['created_at_raw'];
                $row['created_at_iso'] = $row['created_at_raw'] !== '' ? $row['created_at_raw'] : null;
            }
            $items[] = $row;
        }

        $this->renderAdmin('posts/index', [
            'pageTitle'  => $this->typeConfig()[$type]['list'],
            'nav'        => AdminNavigation::build('posts:' . $type),
            'filters'    => $filters,
            'items'      => $items,
            'pagination' => [
                'page'     => $pag['page'] ?? $page,
                'per_page' => $pag['per_page'] ?? $perPage,
                'total'    => $pag['total'] ?? 0,
                'pages'    => $pag['pages'] ?? 1,
            ],
            'type'       => $type,
            'types'      => $this->typeConfig(),
            'urls'       => new LinkGenerator(),
            'statusCounts' => $statusCounts,
        ]);
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
            $this->redirect($this->listUrl($type), 'warning', 'Vyberte položky a požadovanou akci.');
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
            $this->redirect($this->listUrl($type), 'warning', 'Žádné platné položky pro hromadnou akci.');
        }

        $count = count($targetIds);
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
                    DB::query()->table('posts')
                        ->delete()
                        ->whereIn('id', $targetIds)
                        ->execute();
                    $message = 'Položky byly odstraněny.';
                    break;

                default:
                    $this->redirect($this->listUrl($type), 'warning', 'Neznámá hromadná akce.');
            }
        } catch (\Throwable $e) {
            $this->redirect($this->listUrl($type), 'danger', $e->getMessage());
        }

        $this->redirect(
            $this->listUrl($type),
            'success',
            $message . ' (' . $count . ')'
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

        $this->renderAdmin('posts/edit', [
            'pageTitle'      => $this->typeConfig()[$type][$id ? 'edit' : 'create'],
            'nav'            => AdminNavigation::build('posts:' . $type),
            'post'           => $row,
            'terms'          => $terms['byType'],
            'selected'       => $terms['selected'],
            'type'           => $type,
            'types'          => $this->typeConfig(),
            'attachedMedia'  => $this->attachedMediaIds($id),
        ]);
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
            $catIds = [];
            $tagIds = [];
            if ($type === 'post') {
                $catIds = isset($_POST['categories']) ? (array)$_POST['categories'] : [];
                $tagIds = isset($_POST['tags']) ? (array)$_POST['tags'] : [];

                $newCatNames = $this->parseNewTerms((string)($_POST['new_categories'] ?? ''));
                $newTagNames = $this->parseNewTerms((string)($_POST['new_tags'] ?? ''));

                if ($newCatNames !== []) {
                    $catIds = array_merge($catIds, $this->createNewTerms($newCatNames, 'category'));
                }
                if ($newTagNames !== []) {
                    $tagIds = array_merge($tagIds, $this->createNewTerms($newTagNames, 'tag'));
                }
            }

            $selectedThumbId = isset($_POST['selected_thumbnail_id']) ? (int)$_POST['selected_thumbnail_id'] : 0;
            $removeThumb = isset($_POST['remove_thumbnail']) && (int)$_POST['remove_thumbnail'] === 1;
            $thumbId = null;
            if (!$removeThumb) {
                if (!empty($_FILES['thumbnail']) && (int)$_FILES['thumbnail']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $mediaSvc = new MediaService();
                    $up = $mediaSvc->uploadAndCreate($_FILES['thumbnail'], (int)$user['id'], $this->uploadPaths(), 'posts');
                    $thumbId = (int)$up['id'];
                } elseif ($selectedThumbId > 0) {
                    $thumbId = $selectedThumbId;
                }
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

            $this->syncPostMedia($postId, $this->parseAttachedMedia((string)($_POST['attached_media'] ?? '')));

            // ulož vazby termů
            if ($type === 'post') {
                $this->syncTerms($postId, $catIds, $tagIds);
            }

            $this->redirect(
                'admin.php?r=posts&a=edit&id=' . (int)$postId . '&type=' . $type,
                'success',
                'Příspěvek byl vytvořen.'
            );

        } catch (\Throwable $e) {
            $type = $this->requestedType();
            $this->redirect(
                'admin.php?r=posts&a=create&type=' . $type,
                'danger',
                $e->getMessage()
            );
        }
    }

    private function update(): void
    {
        $this->assertCsrf();
        $id = (int)($_GET['id'] ?? 0);
        $type = $this->requestedType();
        if ($id <= 0) {
            $this->redirect($this->listUrl($type), 'danger', 'Chybí ID.');
        }

        $post = (new PostsRepository())->find($id);
        if (!$post) {
            $this->redirect($this->listUrl($type), 'danger', 'Příspěvek nebyl nalezen.');
        }
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
            $catIds = [];
            $tagIds = [];
            if ($type === 'post') {
                $catIds = isset($_POST['categories']) ? (array)$_POST['categories'] : [];
                $tagIds = isset($_POST['tags']) ? (array)$_POST['tags'] : [];

                $newCatNames = $this->parseNewTerms((string)($_POST['new_categories'] ?? ''));
                $newTagNames = $this->parseNewTerms((string)($_POST['new_tags'] ?? ''));

                if ($newCatNames !== []) {
                    $catIds = array_merge($catIds, $this->createNewTerms($newCatNames, 'category'));
                }
                if ($newTagNames !== []) {
                    $tagIds = array_merge($tagIds, $this->createNewTerms($newTagNames, 'tag'));
                }
            }

            // případný nový thumbnail
            $selectedThumbId = isset($_POST['selected_thumbnail_id']) ? (int)$_POST['selected_thumbnail_id'] : 0;
            $removeThumb = isset($_POST['remove_thumbnail']) && (int)$_POST['remove_thumbnail'] === 1;

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

            (new PostsService())->update($id, $upd);

            $this->syncPostMedia($id, $this->parseAttachedMedia((string)($_POST['attached_media'] ?? '')));

            // ulož vazby termů (přepíše existující)
            if ($type === 'post') {
                $this->syncTerms($id, $catIds, $tagIds);
            } else {
                $this->syncTerms($id, [], []);
            }

            $this->redirect(
                'admin.php?r=posts&a=edit&id=' . $id . '&type=' . $type,
                'success',
                'Změny byly uloženy.'
            );

        } catch (\Throwable $e) {
            $this->redirect(
                'admin.php?r=posts&a=edit&id=' . $id . '&type=' . $type,
                'danger',
                $e->getMessage()
            );
        }
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

    private function syncPostMedia(int $postId, array $mediaIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $mediaIds), static fn (int $id): bool => $id > 0)));

        DB::query()->table('post_media')
            ->delete()
            ->where('post_id', '=', $postId)
            ->execute();

        if ($ids === []) {
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
            $this->redirect($this->listUrl($type), 'danger', 'Chybí ID.');
        }

        $post = (new PostsRepository())->find($id);
        if ($post) {
            $rowType = (string)($post['type'] ?? '');
            if (array_key_exists($rowType, $this->typeConfig())) {
                $type = $rowType;
            }
        }

        // smaž vazby i post
        DB::query()->table('post_terms')->delete()->where('post_id','=', $id)->execute();
        DB::query()->table('post_media')->delete()->where('post_id','=', $id)->execute();
        (new PostsRepository())->delete($id);

        $this->redirect($this->listUrl($type), 'success', 'Příspěvek byl odstraněn.');
    }

    private function toggleStatus(): void
    {
        $this->assertCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $type = $this->requestedType();
        if ($id <= 0) {
            $this->redirect($this->listUrl($type), 'danger', 'Chybí ID.');
        }

        $post = (new PostsRepository())->find($id);
        if (!$post) {
            $this->redirect($this->listUrl($type), 'danger', 'Příspěvek nenalezen.');
        }
        $rowType = (string)($post['type'] ?? 'post');
        if (array_key_exists($rowType, $this->typeConfig())) {
            $type = $rowType;
        }

        $new = ((string)$post['status'] === 'publish') ? 'draft' : 'publish';
        (new PostsService())->update($id, ['status'=>$new]);

        $this->redirect(
            $this->listUrl($type),
            'success',
            $new === 'publish' ? 'Publikováno.' : 'Přepnuto na draft.'
        );
    }
}
