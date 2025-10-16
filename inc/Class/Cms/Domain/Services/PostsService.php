<?php
declare(strict_types=1);

namespace Cms\Domain\Services;

use Cms\Domain\Repositories\PostsRepository;
use Cms\Domain\Repositories\TermsRepository;
use Cms\Domain\Repositories\MediaRepository;
use Cms\Validation\Validator;
use Cms\Utils\Slugger;

final class PostsService
{
    public function __construct(
        private readonly PostsRepository $posts = new PostsRepository(),
        private readonly TermsRepository $terms = new TermsRepository(),
        private readonly MediaRepository $media = new MediaRepository()
    ) {}

    /**
     * Vytvoří post + (volitelně) thumbnail + (volitelně) termy.
     * @param array{
     *   title:string, type?:string, status?:string, content?:string,
     *   author_id:int, thumbnail_id?:int|null, terms?:array<int>
     * } $data
     */
    public function create(array $data): int
    {
        $data['type']   = $data['type']   ?? 'post';
        $data['status'] = $data['status'] ?? 'draft';

        $v = (new Validator())
            ->require($data, 'title')
            ->require($data, 'author_id')
            ->enum($data, 'status', ['draft','publish']);

        if (!$v->ok()) {
            throw new \InvalidArgumentException(json_encode($v->errors(), JSON_UNESCAPED_UNICODE));
        }

        $slug = Slugger::uniqueInPosts($data['title'], $data['type']);

        $id = $this->posts->create([
            'title'           => (string)$data['title'],
            'slug'            => $slug,
            'type'            => (string)$data['type'],
            'status'          => (string)$data['status'],
            'content'         => (string)($data['content'] ?? ''),
            'author_id'       => (int)$data['author_id'],
            'thumbnail_id'    => isset($data['thumbnail_id']) ? (int)$data['thumbnail_id'] : null,
            'comments_allowed'=> 1,
            'published_at'    => $data['status']==='publish' ? date('Y-m-d H:i:s') : null,
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => null,
        ]);

        // napojení termů
        if (!empty($data['terms']) && is_array($data['terms'])) {
            foreach ($data['terms'] as $termId) {
                $this->terms->attachToPost($id, (int)$termId);
            }
        }

        return $id;
    }

    public function update(int $id, array $data): int
    {
        $row = $this->posts->find($id);
        if (!$row) throw new \RuntimeException('Post neexistuje');

        $upd = [];
        if (isset($data['title']) && trim((string)$data['title']) !== '') {
            $upd['title'] = (string)$data['title'];
            // pokud se mění title a slug nebyl explicitně zadán, přegeneruj (respektuj unikátnost)
            if (empty($data['slug'])) {
                $upd['slug'] = Slugger::uniqueInPosts($data['title'], $row['type'], $id);
            }
        }
        if (isset($data['slug']) && trim((string)$data['slug']) !== '') {
            $upd['slug'] = Slugger::uniqueInPosts((string)$data['slug'], $row['type'], $id);
        }
        if (isset($data['status'])) {
            $status = (string)$data['status'];
            if (!in_array($status, ['draft','publish'], true)) {
                throw new \InvalidArgumentException('Neplatný status');
            }
            $upd['status'] = $status;
            if ($status === 'publish' && empty($row['published_at'])) {
                $upd['published_at'] = date('Y-m-d H:i:s');
            }
        }
        if (array_key_exists('content',$data)) $upd['content'] = (string)$data['content'];
        if (array_key_exists('thumbnail_id',$data)) $upd['thumbnail_id'] = $data['thumbnail_id'] !== null ? (int)$data['thumbnail_id'] : null;
        if (array_key_exists('comments_allowed',$data)) $upd['comments_allowed'] = (int)$data['comments_allowed'];

        if ($upd === []) return 0;

        $upd['updated_at'] = date('Y-m-d H:i:s');
        return $this->posts->update($id, $upd);
    }
}
