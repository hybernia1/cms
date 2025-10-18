<?php
declare(strict_types=1);

namespace Cms\Http\Front;

use Cms\Front\View\TermsViewModel;
use Core\Database\Init as DB;

final class TermsController extends BaseFrontController
{
    public function __invoke(string $type): void
    {
        $type = trim($type);

        $typeRows = DB::query()
            ->table('terms')
            ->select(["DISTINCT type AS type"])
            ->orderBy('type')
            ->get();

        $availableTypes = array_map(static fn(array $row): string => (string)$row['type'], $typeRows);
        if ($type !== '' && !in_array($type, $availableTypes, true)) {
            $type = '';
        }

        $query = DB::query()
            ->table('terms', 't')
            ->select([
                't.id',
                't.slug',
                't.name',
                't.type',
                't.description',
                't.created_at',
                "SUM(CASE WHEN p.status = 'publish' THEN 1 ELSE 0 END) AS posts_count",
            ])
            ->leftJoin('post_terms pt', 't.id', '=', 'pt.term_id')
            ->leftJoin('posts p', 'pt.post_id', '=', 'p.id')
            ->groupBy(['t.id','t.slug','t.name','t.type','t.description','t.created_at'])
            ->orderBy('t.type')
            ->orderBy('t.name');

        if ($type !== '') {
            $query->where('t.type', '=', $type);
        }

        $terms = $query->get();

        $title = 'Termy';
        if ($type !== '') {
            $title .= ' – ' . ucfirst($type);
        }

        $model = new TermsViewModel(
            $this->viewContext,
            $terms,
            $type !== '' ? $type : null,
            $availableTypes,
            $title
        );

        $this->render('terms', $model);
    }
}
