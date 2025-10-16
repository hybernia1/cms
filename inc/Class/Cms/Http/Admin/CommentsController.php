<?php
declare(strict_types=1);

namespace Cms\Http\Admin;

use Cms\View\ViewEngine;
use Cms\Auth\AuthService;
use Core\Database\Init as DB;

final class CommentsController
{
    private ViewEngine $view;
    private AuthService $auth;

    public function __construct(string $baseViewsPath)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $this->view  = new ViewEngine($baseViewsPath);
        $this->auth  = new AuthService();
    }

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
    private function nav(): array
    {
        return [
            ['key'=>'dashboard','label'=>'Dashboard','href'=>'admin.php?r=dashboard','active'=>false],
            ['key'=>'posts:post','label'=>'Příspěvky','href'=>'admin.php?r=posts&type=post','active'=>false],
            ['key'=>'media','label'=>'Média','href'=>'admin.php?r=media','active'=>false],
            ['key'=>'terms','label'=>'Termy','href'=>'admin.php?r=terms','active'=>false],
            ['key'=>'comments','label'=>'Komentáře','href'=>'admin.php?r=comments','active'=>true],
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

        $this->view->render('comments/index', [
            'pageTitle'   => 'Komentáře',
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
        ]);
        unset($_SESSION['_flash']);
    }

    private function show(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { $this->flash('danger','Chybí ID.'); header('Location: admin.php?r=comments'); exit; }

        $row = DB::query()->table('comments','c')
            ->select([
                'c.*',
                'p.title AS post_title','p.slug AS post_slug','p.type AS post_type'
            ])
            ->join('posts p','c.post_id','=','p.id')
            ->where('c.id','=', $id)
            ->first();

        if (!$row) { $this->flash('danger','Komentář nenalezen.'); header('Location: admin.php?r=comments'); exit; }

        // načti děti (odpovědi)
        $children = DB::query()->table('comments','c')
            ->select(['c.*'])
            ->where('c.parent_id','=', $id)
            ->orderBy('c.created_at','ASC')
            ->get();

        $this->view->render('comments/show', [
            'pageTitle'   => 'Komentář #'.$id,
            'nav'         => $this->nav(),
            'currentUser' => $this->auth->user(),
            'flash'       => $_SESSION['_flash'] ?? null,
            'comment'     => $row,
            'children'    => $children,
            'csrf'        => $this->token(),
        ]);
        unset($_SESSION['_flash']);
    }

    private function setStatus(string $action): void
    {
        $this->assertCsrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { $this->flash('danger','Chybí ID.'); header('Location: admin.php?r=comments'); exit; }

        $targets = [
            'approve' => 'published',
            'draft'   => 'draft',
            'spam'    => 'spam',
        ];
        $new = $targets[$action] ?? 'draft';

        DB::query()->table('comments')->update(['status'=>$new])->where('id','=', $id)->execute();
        $this->flash('success', match($new){
            'published'=>'Schváleno.',
            'spam'=>'Označeno jako spam.',
            default=>'Uloženo jako koncept.'
        });
        $back = $_POST['_back'] ?? 'admin.php?r=comments';
        header('Location: '.$back);
        exit;
    }

    private function delete(): void
    {
        $this->assertCsrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { $this->flash('danger','Chybí ID.'); header('Location: admin.php?r=comments'); exit; }

        // smaž i odpovědi (jedna úroveň; pokud chceš rekurzi, dalo by se)
        DB::query()->table('comments')->delete()->where('parent_id','=', $id)->execute();
        DB::query()->table('comments')->delete()->where('id','=', $id)->execute();

        $this->flash('success','Komentář odstraněn.');
        $back = $_POST['_back'] ?? 'admin.php?r=comments';
        header('Location: '.$back);
        exit;
    }

    private function reply(): void
    {
        $this->assertCsrf();
        $parentId = (int)($_POST['parent_id'] ?? 0);
        $content  = trim((string)($_POST['content'] ?? ''));
        if ($parentId <= 0 || $content === '') {
            $this->flash('danger','Chybí text odpovědi.'); header('Location: admin.php?r=comments'); exit;
        }

        $parent = DB::query()->table('comments')->select(['*'])->where('id','=', $parentId)->first();
        if (!$parent) { $this->flash('danger','Původní komentář nenalezen.'); header('Location: admin.php?r=comments'); exit; }

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
            $parentId,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            date('Y-m-d H:i:s'),
        ])->execute();

        $this->flash('success','Odpověď byla přidána.');
        header('Location: admin.php?r=comments&a=show&id='.$parentId);
        exit;
    }
}
