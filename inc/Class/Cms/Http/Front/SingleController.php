<?php
declare(strict_types=1);

namespace Cms\Http\Front;

use Cms\Domain\Services\CommentTreeService;
use Cms\Front\View\SingleViewModel;
use Core\Database\Init as DB;

final class SingleController extends BaseFrontController
{
    public function __construct(
        FrontServiceContainer $services,
        private readonly CommentTreeService $comments
    ) {
        parent::__construct($services);
    }

    public function post(string $slug): void
    {
        $this->show('post', $slug);
    }

    public function page(string $slug): void
    {
        $this->show('page', $slug);
    }

    private function show(string $type, string $slug): void
    {
        if ($slug === '') {
            $this->renderNotFound();
            return;
        }

        $row = DB::query()->table('posts', 'p')->select(['*'])
            ->where('p.type', '=', $type)
            ->where('p.slug', '=', $slug)
            ->where('p.status', '=', 'publish')
            ->first();

        if (!$row) {
            $this->renderNotFound();
            return;
        }

        $commentsAllowed = (int)($row['comments_allowed'] ?? 1) === 1 && $type === 'post';
        $tree = [];
        if ($commentsAllowed) {
            $tree = $this->comments->publishedTreeForPost((int)$row['id']);
        }

        $termsByType = [];
        if ($type === 'post') {
            $rows = DB::query()
                ->table('post_terms', 'pt')
                ->select(['t.id','t.slug','t.name','t.type'])
                ->join('terms t', 'pt.term_id', '=', 't.id')
                ->where('pt.post_id', '=', (int)$row['id'])
                ->orderBy('t.type')
                ->orderBy('t.name')
                ->get();

            foreach ($rows as $termRow) {
                $termType = (string)($termRow['type'] ?? '');
                if ($termType === '') {
                    continue;
                }
                $termsByType[$termType][] = $termRow;
            }
        }

        $title = trim((string)($row['title'] ?? ''));
        if ($title === '') {
            $title = $type === 'page' ? 'Stránka' : 'Příspěvek';
        }

        $entityKey = $type === 'page' ? 'page' : 'post';

        $model = new SingleViewModel(
            $this->viewContext,
            $entityKey,
            $row,
            $commentsAllowed,
            $tree,
            $this->tokenPublic(),
            $this->readFrontFlash(),
            $termsByType,
            $title
        );

        $this->render($type === 'page' ? 'page' : 'single', $model, ['type' => $type]);
    }
}
