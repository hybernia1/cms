<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Comments;

use Cms\Admin\Http\AjaxResponse;
use Cms\Admin\Http\Ajax\Traits\ProvidesAjaxResponses;
use Core\Database\Init as DB;

final class DeleteHandler
{
    use ProvidesAjaxResponses;
    use CommentsThreadHelpers;

    public function __invoke(): AjaxResponse
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            return $this->errorResponse('Chybí ID komentáře.', 422);
        }

        $targets = $this->collectThreadIds($id);
        if ($targets !== []) {
            DB::query()->table('comments')->delete()->whereIn('id', $targets)->execute();
        }

        $redirect = isset($_POST['_back']) ? (string)$_POST['_back'] : 'admin.php?r=comments';

        return $this->successResponse('Komentář odstraněn.', [
            'redirect' => $redirect,
            'deleted'  => $targets,
        ]);
    }
}
