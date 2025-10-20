<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Comments;

use Cms\Admin\Auth\AuthService;
use Cms\Admin\Http\AjaxResponse;
use Cms\Admin\Http\Ajax\Traits\ProvidesAjaxResponses;
use Cms\Admin\Utils\DateTimeFactory;
use Core\Database\Init as DB;

final class ReplyHandler
{
    use ProvidesAjaxResponses;
    use CommentsThreadHelpers;

    private AuthService $auth;

    public function __construct(?AuthService $auth = null)
    {
        $this->auth = $auth ?? new AuthService();
    }

    public function __invoke(): AjaxResponse
    {
        $parentId = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;
        $content = isset($_POST['content']) ? trim((string)$_POST['content']) : '';

        if ($parentId <= 0 || $content === '') {
            return $this->errorResponse('Chybí text odpovědi.', 422);
        }

        $parent = DB::query()->table('comments')->select(['*'])->where('id', '=', $parentId)->first();
        if (!$parent) {
            return $this->errorResponse('Původní komentář nenalezen.', 404);
        }

        $threadRootId = $this->resolveThreadRootId((int)$parent['id']);
        if ($threadRootId <= 0) {
            $threadRootId = (int)$parent['id'];
        }

        $user = $this->auth->user();
        $authorName = (string)($user['name'] ?? 'Admin');
        $authorEmail = (string)($user['email'] ?? '');

        DB::query()->table('comments')->insert([
            'post_id', 'user_id', 'author_name', 'author_email', 'content', 'status', 'parent_id', 'ip', 'ua', 'created_at'
        ])->values([
            (int)($parent['post_id'] ?? 0),
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

        $redirect = 'admin.php?r=comments&a=show&id=' . $threadRootId;

        return $this->successResponse('Odpověď byla přidána.', [
            'redirect' => $redirect,
            'parent_id' => $parentId,
            'thread_id' => $threadRootId,
        ]);
    }
}
