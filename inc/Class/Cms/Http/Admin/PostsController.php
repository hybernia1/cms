<?php
declare(strict_types=1);

namespace Cms\Http\Admin;

use Cms\Domain\Repositories\PostsRepository;
use Cms\Domain\Services\PostsService;
use Cms\Domain\Services\MediaService;
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
        ]);
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

        $terms = $this->termsData($id ?: null);

        $this->renderAdmin('posts/edit', [
            'pageTitle' => $this->typeConfig()[$type][$id ? 'edit' : 'create'],
            'nav'       => AdminNavigation::build('posts:' . $type),
            'post'      => $row,
            'terms'     => $terms['byType'],
            'selected'  => $terms['selected'],
            'type'      => $type,
            'types'     => $this->typeConfig(),
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
            $catIds = isset($_POST['categories']) ? (array)$_POST['categories'] : [];
            $tagIds = isset($_POST['tags']) ? (array)$_POST['tags'] : [];

            $thumbId = null;
            if (!empty($_FILES['thumbnail']) && (int)$_FILES['thumbnail']['error'] !== UPLOAD_ERR_NO_FILE) {
                $mediaSvc = new MediaService();
                $up = $mediaSvc->uploadAndCreate($_FILES['thumbnail'], (int)$user['id'], $this->uploadPaths(), 'posts');
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
            $catIds = isset($_POST['categories']) ? (array)$_POST['categories'] : [];
            $tagIds = isset($_POST['tags']) ? (array)$_POST['tags'] : [];

            // případný nový thumbnail
            if (!empty($_FILES['thumbnail']) && (int)$_FILES['thumbnail']['error'] !== UPLOAD_ERR_NO_FILE) {
                $mediaSvc = new MediaService();
                $up = $mediaSvc->uploadAndCreate($_FILES['thumbnail'], (int)$user['id'], $this->uploadPaths(), 'posts');
                $upd['thumbnail_id'] = (int)$up['id'];
            }

            (new PostsService())->update($id, $upd);

            // ulož vazby termů (přepíše existující)
            $this->syncTerms($id, $catIds, $tagIds);

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
