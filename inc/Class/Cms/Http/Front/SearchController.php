<?php
declare(strict_types=1);

namespace Cms\Http\Front;

use Cms\Front\View\SearchViewModel;
use Core\Database\Init as DB;

final class SearchController extends BaseFrontController
{
    public function __invoke(string $query): void
    {
        $q = trim($query);
        $items = [];
        if ($q !== '') {
            $like = '%' . $q . '%';
            $items = DB::query()
                ->table('posts', 'p')
                ->select(['p.id','p.title','p.slug','p.created_at','p.type'])
                ->where('p.status', '=', 'publish')
                ->whereLike('p.title', $like)
                ->orderBy('p.created_at', 'DESC')
                ->limit(50)
                ->get();
        }

        $title = $q === '' ? 'Hledání' : 'Hledání: ' . $q;
        $this->render('search', new SearchViewModel($this->viewContext, $items, $q, $title));
    }
}
