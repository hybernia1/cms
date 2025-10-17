<?php
declare(strict_types=1);

namespace Cms\Http\Admin;

use Core\Database\Init as DB;
use Cms\Utils\AdminNavigation;
use Cms\Utils\DateTimeFactory;
use Cms\Settings\CmsSettings;

final class CommentsController extends BaseAdminController
{
    public function handle(string $action): void
    {
        switch ($action) {
            case 'index':
            default:
                $this->index(); return;

            case 'show':
                $this->show(); return;

            case 'approve':
            case 'draft':
            case 'spam':
                $this->setStatus($action); return;

            case 'delete':
                $this->delete(); return;

            case 'reply':
                $this->reply(); return;
        }
    }

    // --------- helpers ----------
    // --------- actions ----------
    private function index(): void
    {
        $filters = [
            'status' => (string)($_GET['status'] ?? ''), // draft|published|spam
            'q'      => (string)($_GET['q'] ?? ''),      // hledání v obsahu/jménu/emailu
            'post'   => (string)($_GET['post'] ?? ''),   // slug nebo id
        ];
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;

        $q = DB::query()->table('comments','c')
            ->select([
                'c.id','c.post_id','c.user_id','c.author_name','c.author_email',
                'c.content','c.status','c.parent_id','c.created_at',
                'p.title AS post_title','p.slug AS post_slug','p.type AS post_type'
            ])
            ->join('posts p','c.post_id','=','p.id')
            ->orderBy('c.created_at','DESC');

        if ($filters['status'] !== '') { $q->where('c.status','=', $filters['status']); }
        if ($filters['q'] !== '') {
            $like = '%' . $filters['q'] . '%';
            $q->where(function($w) use ($like) {
                $w->whereLike('c.content',$like)
                  ->orWhere('c.author_name','LIKE',$like)
                  ->orWhere('c.author_email','LIKE',$like);
            });
        }
        if ($filters['post'] !== '') {
            if (ctype_digit($filters['post'])) {
                $q->where('c.post_id','=', (int)$filters['post']);
            } else {
                $q->where('p.slug','=', $filters['post']);
            }
        }

        $pag = $q->paginate($page, $perPage);

        $settings = new CmsSettings();
        $items = [];
        foreach (($pag['items'] ?? []) as $row) {
            $createdAt = isset($row['created_at']) ? (string)$row['created_at'] : null;
            $row['created_at_raw'] = $createdAt ?? '';
            $created = DateTimeFactory::fromStorage($createdAt);
            if ($created) {
                $row['created_at_display'] = $settings->formatDateTime($created);
                $row['created_at_iso'] = $created->format(\DateTimeInterface::ATOM);
            } else {
                $row['created_at_display'] = $createdAt ?? '';
                $row['created_at_iso'] = $createdAt ?? null;
            }
            $items[] = $row;
        }

        $this->renderAdmin('comments/index', [
            'pageTitle'  => 'Komentáře',
            'nav'        => AdminNavigation::build('comments'),
            'filters'    => $filters,
            'items'      => $items,
            'pagination' => [
                'page'     => $pag['page'] ?? $page,
                'per_page' => $pag['per_page'] ?? $perPage,
                'total'    => $pag['total'] ?? 0,
                'pages'    => $pag['pages'] ?? 1,
            ],
        ]);
    }

    private function show(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('admin.php?r=comments', 'danger', 'Chybí ID.');
        }

        $row = DB::query()->table('comments','c')
            ->select([
                'c.*',
                'p.title AS post_title','p.slug AS post_slug','p.type AS post_type'
            ])
            ->join('posts p','c.post_id','=','p.id')
            ->where('c.id','=', $id)
            ->first();

        if (!$row) {
            $this->redirect('admin.php?r=comments', 'danger', 'Komentář nenalezen.');
        }

        $threadRootId = $this->resolveThreadRootId((int)$row['id']);
        $children = [];
        $childIds = [];
        if ($threadRootId > 0) {
            $threadIds = $this->collectThreadIds($threadRootId);
            if ($threadIds) {
                $childIds = array_values(array_diff($threadIds, [$threadRootId, $id]));
            }
        }
        if ($childIds) {
            $children = DB::query()->table('comments','c')
                ->select(['c.*'])
                ->whereIn('c.id', $childIds)
                ->orderBy('c.created_at','ASC')
                ->get();
        }

        $this->renderAdmin('comments/show', [
            'pageTitle' => 'Komentář #' . $id,
            'nav'       => AdminNavigation::build('comments'),
            'comment'   => $row,
            'children'  => $children,
            'replyParentId' => $threadRootId,
        ]);
    }

    private function setStatus(string $action): void
    {
        $this->assertCsrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('admin.php?r=comments', 'danger', 'Chybí ID.');
        }

        $targets = [
            'approve' => 'published',
            'draft'   => 'draft',
            'spam'    => 'spam',
        ];
        $new = $targets[$action] ?? 'draft';

        DB::query()->table('comments')->update(['status'=>$new])->where('id','=', $id)->execute();
        $this->redirect(
            (string)($_POST['_back'] ?? 'admin.php?r=comments'),
            'success',
            match ($new) {
                'published' => 'Schváleno.',
                'spam'      => 'Označeno jako spam.',
                default     => 'Uloženo jako koncept.',
            }
        );
    }

    private function delete(): void
    {
        $this->assertCsrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('admin.php?r=comments', 'danger', 'Chybí ID.');
        }

        $targetIds = $this->collectThreadIds($id);
        if ($targetIds) {
            DB::query()->table('comments')->delete()->whereIn('id', $targetIds)->execute();
        }

        $this->redirect((string)($_POST['_back'] ?? 'admin.php?r=comments'), 'success', 'Komentář odstraněn.');
    }

    private function reply(): void
    {
        $this->assertCsrf();
        $parentId = (int)($_POST['parent_id'] ?? 0);
        $content  = trim((string)($_POST['content'] ?? ''));
        if ($parentId <= 0 || $content === '') {
            $this->redirect('admin.php?r=comments', 'danger', 'Chybí text odpovědi.');
        }

        $parent = DB::query()->table('comments')->select(['*'])->where('id','=', $parentId)->first();
        if (!$parent) {
            $this->redirect('admin.php?r=comments', 'danger', 'Původní komentář nenalezen.');
        }

        $threadRootId = $this->resolveThreadRootId((int)$parent['id']);
        if ($threadRootId <= 0) {
            $threadRootId = (int)$parent['id'];
        }

        $user = $this->auth->user();
        $authorName  = (string)($user['name'] ?? 'Admin');
        $authorEmail = (string)($user['email'] ?? '');

        DB::query()->table('comments')->insert([
            'post_id','user_id','author_name','author_email','content','status','parent_id','ip','ua','created_at'
        ])->values([
            (int)$parent['post_id'],
            (int)($user['id'] ?? 0),
            $authorName,
            $authorEmail,
            $content,
            'published',
            $threadRootId,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            DateTimeFactory::nowString(),
        ])->execute();

        $this->redirect('admin.php?r=comments&a=show&id=' . $threadRootId, 'success', 'Odpověď byla přidána.');
    }

    private function collectThreadIds(int $rootId): array
    {
        if ($rootId <= 0) {
            return [];
        }

        $ids = [$rootId];
        $queue = [$rootId];

        while ($queue) {
            $children = DB::query()->table('comments')->select(['id'])->whereIn('parent_id', $queue)->get();
            $queue = [];
            foreach ($children as $child) {
                $childId = (int)($child['id'] ?? 0);
                if ($childId > 0 && !in_array($childId, $ids, true)) {
                    $ids[] = $childId;
                    $queue[] = $childId;
                }
            }
        }

        return $ids;
    }

    private function resolveThreadRootId(int $commentId): int
    {
        if ($commentId <= 0) {
            return 0;
        }

        $currentId = $commentId;
        $guard = 0;
        while ($currentId > 0 && $guard < 20) {
            $row = DB::query()->table('comments')->select(['id','parent_id'])->where('id','=', $currentId)->first();
            if (!$row) {
                return $commentId;
            }
            $parentId = (int)($row['parent_id'] ?? 0);
            if ($parentId <= 0 || $parentId === $currentId) {
                return (int)$row['id'];
            }
            $currentId = $parentId;
            $guard++;
        }

        return $commentId;
    }
}
