<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Terms;

use Cms\Admin\Domain\Repositories\TermsRepository;
use Cms\Admin\Domain\Services\TermsService;
use Cms\Admin\Http\Ajax\Traits\ProvidesAjaxResponses;
use Cms\Admin\Http\AjaxResponse;
use Cms\Admin\Utils\Slugger;
use Core\Database\Init as DB;
use Throwable;

final class SaveHandler
{
    use ProvidesAjaxResponses;
    use TermsHelpers;

    public function __invoke(): AjaxResponse
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $name = isset($_POST['name']) ? trim((string)$_POST['name']) : '';
        $slug = isset($_POST['slug']) ? trim((string)$_POST['slug']) : '';
        $type = $this->normalizeType(isset($_POST['type']) ? (string)$_POST['type'] : null);
        $description = isset($_POST['description']) ? (string)$_POST['description'] : '';

        if ($name === '') {
            return $this->errorResponse('Název je povinný.', 422);
        }

        $repository = new TermsRepository();

        if ($id > 0) {
            $existing = $repository->find($id);
            if (!$existing) {
                return $this->errorResponse('Term nenalezen.', 404);
            }

            $type = $this->normalizeType($existing['type'] ?? $type);

            if ($slug === '') {
                $slug = Slugger::make($name);
            }

            try {
                $duplicate = $repository->findBySlug($slug);
                if ($duplicate && (int)$duplicate['id'] !== $id) {
                    $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 3);
                }

                DB::query()
                    ->table('terms')
                    ->update([
                        'name'        => $name,
                        'slug'        => $slug,
                        'type'        => $type,
                        'description' => $description,
                        'created_at'  => $existing['created_at'] ?? null,
                    ])
                    ->where('id', '=', $id)
                    ->execute();
            } catch (Throwable $exception) {
                $message = trim((string)$exception->getMessage()) ?: 'Uložení termu selhalo.';

                return $this->errorResponse($message, 500);
            }

            return $this->successResponse('Změny byly uloženy.', [
                'redirect' => $this->buildRedirectUrl($type, ['a' => 'edit', 'id' => $id]),
                'id'       => $id,
                'type'     => $type,
            ]);
        }

        if ($slug === '') {
            $slug = Slugger::make($name);
        }

        try {
            $service = new TermsService($repository);
            $newId = $service->create($name, $type, $slug, $description);
        } catch (Throwable $exception) {
            $message = trim((string)$exception->getMessage()) ?: 'Vytvoření termu selhalo.';

            return $this->errorResponse($message, 500);
        }

        return $this->successResponse('Term byl vytvořen.', [
            'redirect' => $this->buildRedirectUrl($type, ['a' => 'edit', 'id' => $newId]),
            'id'       => $newId,
            'type'     => $type,
        ]);
    }
}
