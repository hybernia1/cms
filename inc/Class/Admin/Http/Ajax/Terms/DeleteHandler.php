<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Terms;

use Cms\Admin\Domain\Repositories\TermsRepository;
use Cms\Admin\Http\Ajax\Traits\ProvidesAjaxResponses;
use Cms\Admin\Http\AjaxResponse;
use Core\Database\Init as DB;
use Throwable;

final class DeleteHandler
{
    use ProvidesAjaxResponses;
    use TermsHelpers;

    public function __invoke(): AjaxResponse
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($id <= 0) {
            return $this->errorResponse('Chybí ID.', 422);
        }

        $repository = new TermsRepository();
        $term = $repository->find($id);
        if (!$term) {
            return $this->errorResponse('Term nenalezen.', 404);
        }

        $type = $this->normalizeType($term['type'] ?? null);

        try {
            DB::query()->table('post_terms')->delete()->where('term_id', '=', $id)->execute();
            DB::query()->table('terms')->delete()->where('id', '=', $id)->execute();
        } catch (Throwable $exception) {
            $message = trim((string)$exception->getMessage()) ?: 'Smazání termu selhalo.';

            return $this->errorResponse($message, 500);
        }

        return $this->successResponse('Term byl odstraněn.', [
            'redirect'    => $this->buildRedirectUrl($type),
            'deleted_id'  => $id,
            'type'        => $type,
        ]);
    }
}
