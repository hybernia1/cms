<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Terms;

use Cms\Admin\Http\Ajax\Traits\ProvidesAjaxResponses;
use Cms\Admin\Http\AjaxResponse;
use Core\Database\Init as DB;
use Throwable;

final class BulkHandler
{
    use ProvidesAjaxResponses;
    use TermsHelpers;

    public function __invoke(): AjaxResponse
    {
        $action = isset($_POST['bulk_action']) ? (string)$_POST['bulk_action'] : '';
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
        $type = $this->normalizeType(isset($_POST['type']) ? (string)$_POST['type'] : null);

        if ($ids === [] || $action === '') {
            return $this->errorResponse('Vyberte položky a požadovanou akci.', 422, null, 'warning');
        }

        if ($action !== 'delete') {
            return $this->errorResponse('Neznámá hromadná akce.', 422, null, 'warning');
        }

        $existing = DB::query()
            ->table('terms')
            ->select(['id'])
            ->where('type', '=', $type)
            ->whereIn('id', $ids)
            ->get();

        $targetIds = [];
        foreach ($existing as $row) {
            $targetIds[] = (int)($row['id'] ?? 0);
        }
        $targetIds = array_values(array_filter($targetIds, static fn (int $id): bool => $id > 0));

        if ($targetIds === []) {
            return $this->errorResponse('Žádné platné položky pro hromadnou akci.', 422, null, 'warning');
        }

        try {
            DB::query()->table('post_terms')->delete()->whereIn('term_id', $targetIds)->execute();
            DB::query()->table('terms')->delete()->whereIn('id', $targetIds)->execute();
        } catch (Throwable $exception) {
            $message = trim((string)$exception->getMessage()) ?: 'Hromadná akce se nezdařila.';

            return $this->errorResponse($message, 500);
        }

        $count = count($targetIds);

        return $this->successResponse('Hromadná akce dokončena (' . $count . ')', [
            'redirect'    => $this->buildRedirectUrl($type),
            'affected'    => $count,
            'deleted_ids' => $targetIds,
            'type'        => $type,
        ]);
    }
}
