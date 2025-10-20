<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Media;

use Cms\Admin\Auth\AuthService;
use Cms\Admin\Domain\Services\MediaService;
use Cms\Admin\Http\AjaxResponse;
use Cms\Admin\Http\Ajax\Traits\ProvidesAjaxResponses;
use Cms\Admin\Utils\UploadPathFactory;
use Throwable;

final class EditorUploadHandler
{
    use ProvidesAjaxResponses;

    private MediaService $mediaService;
    private AuthService $auth;

    public function __construct(?MediaService $mediaService = null, ?AuthService $auth = null)
    {
        $this->mediaService = $mediaService ?? new MediaService();
        $this->auth = $auth ?? new AuthService();
    }

    public function __invoke(): AjaxResponse
    {
        try {
            $user = $this->auth->user();
            if (!$user) {
                return $this->errorResponse('Nejste přihlášeni.', 401, null, null);
            }

            if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
                return $this->errorResponse('Nebyl vybrán žádný soubor.', 422, null, null);
            }

            $file = $_FILES['file'];
            if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                return $this->errorResponse('Nebyl vybrán žádný soubor.', 422, null, null);
            }
            if ((int)($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                return $this->errorResponse('Soubor se nepodařilo nahrát.', 400, null, null);
            }

            $postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;

            $item = $this->mediaService->uploadAndCreate(
                $file,
                (int)($user['id'] ?? 0),
                UploadPathFactory::forUploads(),
                'posts',
                $postId > 0 ? $postId : null
            );

            return $this->successResponse(null, ['item' => $item], null);
        } catch (Throwable $exception) {
            $message = trim((string)$exception->getMessage()) ?: 'Soubor se nepodařilo nahrát.';

            return $this->errorResponse($message, 400, $message, null);
        }
    }
}
