<?php
declare(strict_types=1);

namespace Cms\Http\Front;

use Cms\Domain\Services\CommentTreeService;
use Cms\Domain\Services\CommentsService;
use Core\Database\Init as DB;

final class CommentsController extends BaseFrontController
{
    public function __construct(
        FrontServiceContainer $services,
        private readonly CommentsService $commentsService = new CommentsService(),
        private readonly CommentTreeService $treeService = new CommentTreeService()
    ) {
        parent::__construct($services);
    }

    public function submit(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->renderNotFound();
            return;
        }

        try {
            $this->assertCsrfPublic();

            if (!empty($_POST['website'])) {
                $this->writeFrontFlash('success', 'Komentář byl odeslán ke schválení.');
                $this->redirectBack();
            }

            $now = time();
            $last = (int)($_SESSION['_last_comment_ts'] ?? 0);
            if ($now - $last < 30) {
                throw new \RuntimeException('Zkuste to prosím znovu za chvíli.');
            }
            $_SESSION['_last_comment_ts'] = $now;

            $postId = (int)($_POST['post_id'] ?? 0);
            $post = DB::query()->table('posts')->select(['id','slug','type','comments_allowed','status'])
                ->where('id', '=', $postId)
                ->first();

            if (!$post || (string)$post['status'] !== 'publish') {
                throw new \RuntimeException('Příspěvek neexistuje.');
            }

            if ((int)($post['comments_allowed'] ?? 1) !== 1 || (string)($post['type'] ?? '') !== 'post') {
                throw new \RuntimeException('Komentáře jsou u tohoto příspěvku zakázány.');
            }

            $parentId = (int)($_POST['parent_id'] ?? 0);
            if ($parentId > 0) {
                $parentId = $this->treeService->threadRootForReply($parentId, $postId);
            }

            $user = $this->services->frontUser();
            $isAdmin = $this->isAdmin($user);

            if ($user) {
                $authorName = (string)($user['name'] ?? 'Uživatel');
                $authorEmail = (string)($user['email'] ?? '');
                $userId = (int)($user['id'] ?? 0);
            } else {
                $authorName = trim((string)($_POST['author_name'] ?? ''));
                $authorEmail = trim((string)($_POST['author_email'] ?? ''));
                $userId = 0;
            }

            $content = trim((string)($_POST['content'] ?? ''));
            if ($authorName === '' || $content === '') {
                throw new \RuntimeException('Jméno i text komentáře jsou povinné.');
            }
            if ($authorEmail !== '' && !filter_var($authorEmail, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Neplatný e-mail.');
            }

            $status = $isAdmin ? 'published' : 'draft';

            $this->commentsService->create([
                'post_id'      => $postId,
                'user_id'      => $userId > 0 ? $userId : null,
                'parent_id'    => $parentId ?: null,
                'author_name'  => $authorName,
                'author_email' => $authorEmail !== '' ? $authorEmail : null,
                'content'      => $content,
                'status'       => $status,
            ]);

            $message = $isAdmin ? 'Komentář byl publikován.' : 'Komentář byl odeslán ke schválení.';
            $this->writeFrontFlash('success', $message);
            $this->redirectToPost((string)$post['slug']);
        } catch (\Throwable $e) {
            $this->writeFrontFlash('danger', $e->getMessage());
            $this->redirectBack();
        }
    }
}
