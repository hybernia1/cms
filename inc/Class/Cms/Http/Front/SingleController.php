<?php
declare(strict_types=1);

namespace Cms\Http\Front;

use Cms\Domain\Services\CommentTreeService;
use Cms\Front\View\SingleViewModel;
use Cms\Utils\UploadPathFactory;
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

        $thumbnail = $this->resolveThumbnail($row);

        if ($thumbnail !== null) {
            $row['thumbnail'] = $thumbnail;
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

    /**
     * @param array<string,mixed> $post
     * @return array<string,mixed>|null
     */
    private function resolveThumbnail(array $post): ?array
    {
        $thumbnailId = isset($post['thumbnail_id']) ? (int)$post['thumbnail_id'] : 0;
        if ($thumbnailId <= 0) {
            return null;
        }

        $media = DB::query()->table('media')->select(['id','url','mime','meta','rel_path'])
            ->where('id', '=', $thumbnailId)
            ->first();

        if (!$media) {
            return null;
        }

        $mime = (string)($media['mime'] ?? '');
        if ($mime === '' || !str_starts_with($mime, 'image/')) {
            return null;
        }

        $url = (string)($media['url'] ?? '');
        if ($url === '') {
            return null;
        }

        $meta = $this->decodeMeta($media['meta'] ?? null);

        return [
            'id'      => (int)($media['id'] ?? 0),
            'url'     => $url,
            'mime'    => $mime,
            'webpUrl' => $this->resolveWebpUrl($meta['webp'] ?? null),
            'width'   => isset($meta['w']) ? (int)$meta['w'] : null,
            'height'  => isset($meta['h']) ? (int)$meta['h'] : null,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeMeta(mixed $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function resolveWebpUrl(mixed $relative): ?string
    {
        if (!is_string($relative) || trim($relative) === '') {
            return null;
        }

        try {
            $paths = UploadPathFactory::forUploads();
            return $paths->publicUrl($relative);
        } catch (\Throwable) {
            return null;
        }
    }
}
