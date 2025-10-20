<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Media;

use Cms\Admin\Http\AjaxResponse;
use Cms\Admin\Http\Ajax\Traits\ProvidesAjaxResponses;
use Core\Database\Init as DB;
use Cms\Admin\Utils\UploadPathFactory;
use Throwable;

final class DeleteHandler
{
    use ProvidesAjaxResponses;

    public function __invoke(): AjaxResponse
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            return $this->errorResponse('Chybí ID.', 422);
        }

        try {
            $row = DB::query()->table('media')->select(['id', 'rel_path'])->where('id', '=', $id)->first();

            DB::query()->table('media')->delete()->where('id', '=', $id)->execute();
            DB::query()->table('post_media')->delete()->where('media_id', '=', $id)->execute();

            if ($row && !empty($row['rel_path'])) {
                $paths = UploadPathFactory::forUploads();
                try {
                    $absolute = $paths->absoluteFromRelative((string)$row['rel_path']);
                    if (is_file($absolute)) {
                        @unlink($absolute);
                    }
                } catch (Throwable) {
                    // ignore issues with resolving path
                }
            }

            return $this->successResponse('Soubor odstraněn.', [
                'redirect' => 'admin.php?r=media',
                'deleted_id' => $id,
            ]);
        } catch (Throwable $exception) {
            $message = trim((string)$exception->getMessage()) ?: 'Soubor se nepodařilo odstranit.';

            return $this->errorResponse($message, 500);
        }
    }
}
