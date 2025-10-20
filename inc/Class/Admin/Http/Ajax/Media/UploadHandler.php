<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Media;

use Cms\Admin\Auth\AuthService;
use Cms\Admin\Domain\Services\MediaService;
use Cms\Admin\Http\AjaxResponse;
use Cms\Admin\Http\Ajax\Traits\ProvidesAjaxResponses;
use Cms\Admin\Utils\UploadPathFactory;
use Core\Files\PathResolver;
use Throwable;

final class UploadHandler
{
    use ProvidesAjaxResponses;

    private MediaService $mediaService;
    private AuthService $auth;
    private PathResolver $paths;

    public function __construct(?MediaService $mediaService = null, ?AuthService $auth = null, ?PathResolver $paths = null)
    {
        $this->mediaService = $mediaService ?? new MediaService();
        $this->auth = $auth ?? new AuthService();
        $this->paths = $paths ?? UploadPathFactory::forUploads();
    }

    public function __invoke(): AjaxResponse
    {
        try {
            $user = $this->auth->user();
            if (!$user) {
                return $this->errorResponse('Nejste přihlášeni.', 401);
            }

            if (!isset($_FILES['files'])) {
                return $this->errorResponse('Nebyl vybrán žádný soubor.', 422);
            }

            $files = $_FILES['files'];
            $uploadedCount = 0;

            if (is_array($files['name'])) {
                $count = count($files['name']);
                for ($i = 0; $i < $count; $i++) {
                    $error = (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                    if ($error === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    $filePayload = [
                        'name'     => $files['name'][$i] ?? '',
                        'type'     => $files['type'][$i] ?? '',
                        'tmp_name' => $files['tmp_name'][$i] ?? '',
                        'error'    => $error,
                        'size'     => $files['size'][$i] ?? 0,
                    ];
                    $this->mediaService->uploadAndCreate($filePayload, (int)($user['id'] ?? 0), $this->paths, 'media');
                    $uploadedCount++;
                }
            } elseif ((int)($files['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $this->mediaService->uploadAndCreate($files, (int)($user['id'] ?? 0), $this->paths, 'media');
                $uploadedCount++;
            }

            if ($uploadedCount === 0) {
                return $this->errorResponse('Nic se nenahrálo.', 422);
            }

            $message = 'Nahráno souborů: ' . $uploadedCount . '.';

            return $this->successResponse($message, [
                'redirect' => 'admin.php?r=media',
                'uploaded' => $uploadedCount,
            ]);
        } catch (Throwable $exception) {
            $message = trim((string)$exception->getMessage()) ?: 'Soubor se nepodařilo nahrát.';

            return $this->errorResponse($message, 500);
        }
    }
}
