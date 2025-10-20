<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Media;

use Cms\Admin\Domain\Services\MediaService;
use Cms\Admin\Http\AjaxResponse;
use Cms\Admin\Http\Ajax\Traits\ProvidesAjaxResponses;
use Cms\Admin\Utils\UploadPathFactory;
use Throwable;

final class OptimizeHandler
{
    use ProvidesAjaxResponses;

    private MediaService $mediaService;

    public function __construct(?MediaService $mediaService = null)
    {
        $this->mediaService = $mediaService ?? new MediaService();
    }

    public function __invoke(): AjaxResponse
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            return $this->errorResponse('Chybí ID.', 422);
        }

        try {
            $created = $this->mediaService->optimizeWebp($id, UploadPathFactory::forUploads());
            $message = $created ? 'WebP varianta byla vytvořena.' : 'WebP varianta již existuje.';
            $flashType = $created ? 'success' : 'info';

            return $this->successResponse($message, [
                'redirect' => 'admin.php?r=media',
                'created' => $created,
            ], $flashType);
        } catch (Throwable $exception) {
            $message = trim((string)$exception->getMessage()) ?: 'Optimalizace se nezdařila.';

            return $this->errorResponse($message, 500);
        }
    }
}
