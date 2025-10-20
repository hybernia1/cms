<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Comments;

use Cms\Admin\Http\AjaxResponse;
use Cms\Admin\Http\Ajax\Traits\ProvidesAjaxResponses;
use Core\Database\Init as DB;

final class StatusHandler
{
    use ProvidesAjaxResponses;

    public function __invoke(): AjaxResponse
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $status = isset($_POST['status']) ? (string)$_POST['status'] : '';
        if ($id <= 0) {
            return $this->errorResponse('Chybí ID komentáře.', 422);
        }

        $allowed = [
            'published' => 'Schváleno.',
            'draft'     => 'Uloženo jako koncept.',
            'spam'      => 'Označeno jako spam.',
        ];

        if (!isset($allowed[$status])) {
            return $this->errorResponse('Neplatný stav komentáře.', 422);
        }

        DB::query()->table('comments')->update(['status' => $status])->where('id', '=', $id)->execute();

        $redirect = isset($_POST['_back']) ? (string)$_POST['_back'] : 'admin.php?r=comments';

        return $this->successResponse($allowed[$status], [
            'redirect' => $redirect,
            'status'   => $status,
            'id'       => $id,
        ]);
    }
}
