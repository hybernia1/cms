<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Media;

use Cms\Admin\Http\AjaxResponse;
use Cms\Admin\Http\Ajax\Traits\ProvidesAjaxResponses;
use Core\Database\Init as DB;

final class LibraryHandler
{
    use ProvidesAjaxResponses;

    public function __invoke(): AjaxResponse
    {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 60;
        $limit = max(1, min(100, $limit));
        $type = isset($_GET['type']) ? trim((string)$_GET['type']) : '';

        $query = DB::query()->table('media', 'm')
            ->select(['m.id', 'm.url', 'm.mime', 'm.type', 'm.created_at'])
            ->orderBy('m.created_at', 'DESC')
            ->limit($limit);

        if ($type !== '') {
            $query->where('m.type', '=', $type);
        }

        $rows = $query->get();
        $items = [];
        foreach ($rows as $row) {
            $url = (string)($row['url'] ?? '');
            $path = $url !== '' ? parse_url($url, PHP_URL_PATH) : '';
            $basename = is_string($path) ? basename($path) : '';
            if ($basename === '') {
                $basename = 'ID ' . (int)($row['id'] ?? 0);
            }

            $items[] = [
                'id'   => (int)($row['id'] ?? 0),
                'url'  => $url,
                'mime' => (string)($row['mime'] ?? ''),
                'type' => (string)($row['type'] ?? ''),
                'name' => $basename,
            ];
        }

        return $this->successResponse(null, ['items' => $items], null);
    }
}
