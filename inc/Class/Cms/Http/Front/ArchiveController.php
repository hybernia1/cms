<?php
declare(strict_types=1);

namespace Cms\Http\Front;

use Cms\Front\View\ArchiveViewModel;
use Core\Database\Init as DB;

final class ArchiveController extends BaseFrontController
{
    public function byType(string $type): void
    {
        $items = DB::query()
            ->table('posts', 'p')
            ->select(['p.id','p.title','p.slug','p.created_at'])
            ->where('p.type', '=', $type)
            ->where('p.status', '=', 'publish')
            ->orderBy('p.created_at', 'DESC')
            ->limit(50)
            ->get();

        $title = 'Archiv – ' . $this->typeLabel($type);
        $model = new ArchiveViewModel($this->viewContext, $items, $this->typeLabel($type), null, $title);
        $this->render('archive', $model, ['type' => $type]);
    }

    public function byTerm(string $slug, ?string $type = null): void
    {
        if ($slug === '') {
            $this->renderNotFound();
            return;
        }

        $termQuery = DB::query()
            ->table('terms')
            ->select(['id','name','slug','type'])
            ->where('slug', '=', $slug);

        if ($type !== null) {
            $termQuery->where('type', '=', $type);
        }

        $term = $termQuery->first();
        if (!$term) {
            $this->renderNotFound();
            return;
        }

        $items = DB::query()
            ->table('post_terms', 'pt')
            ->select(['p.id','p.title','p.slug','p.created_at'])
            ->join('posts p', 'pt.post_id', '=', 'p.id')
            ->where('pt.term_id', '=', (int)$term['id'])
            ->where('p.status', '=', 'publish')
            ->orderBy('p.created_at', 'DESC')
            ->limit(50)
            ->get();

        $typeLabel = match ((string)($term['type'] ?? '')) {
            'category' => 'Kategorie',
            'tag'      => 'Štítek',
            default    => ucfirst((string)($term['type'] ?? 'Term')),
        };

        $label = $typeLabel . ': ' . (string)$term['name'];
        $model = new ArchiveViewModel($this->viewContext, $items, $label, $term, 'Archiv – ' . $label);
        $this->render('archive', $model);
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'post' => 'Příspěvky',
            'page' => 'Stránky',
            default => ucfirst($type),
        };
    }
}
