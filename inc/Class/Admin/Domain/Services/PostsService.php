<?php
declare(strict_types=1);

namespace Cms\Admin\Domain\Services;

use Cms\Admin\Domain\Repositories\PostsRepository;
use Cms\Admin\Domain\Repositories\TermsRepository;
use Cms\Admin\Domain\Repositories\MediaRepository;
use Cms\Admin\Utils\DateTimeFactory;
use Cms\Admin\Utils\Slugger;
use Cms\Admin\Validation\Validator;

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

        $allowedTypes = ['post', 'page'];
        if (!in_array((string)$data['type'], $allowedTypes, true)) {
            $data['type'] = 'post';
        }

        $v = (new Validator())
            ->require($data, 'title')
            ->require($data, 'author_id')
            ->enum($data, 'status', ['draft','publish']);

        if (!$v->ok()) {
            throw new \InvalidArgumentException(json_encode($v->errors(), JSON_UNESCAPED_UNICODE));
        }

        $slug = Slugger::uniqueInPosts($data['title'], (string)$data['type']);

        $now = DateTimeFactory::now();

        $now = DateTimeFactory::now();

        $id = $this->posts->create([
            'title'           => (string)$data['title'],
            'slug'            => $slug,
            'type'            => (string)$data['type'],
            'status'          => (string)$data['status'],
            'content'         => (string)($data['content'] ?? ''),
            'author_id'       => (int)$data['author_id'],
            'thumbnail_id'    => isset($data['thumbnail_id']) ? (int)$data['thumbnail_id'] : null,
            'comments_allowed'=> 1,
            'published_at'    => $data['status']==='publish' ? DateTimeFactory::formatForStorage($now) : null,
            'created_at'      => DateTimeFactory::formatForStorage($now),
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
        $typeForSlug = in_array((string)($row['type'] ?? ''), ['post', 'page'], true)
            ? (string)$row['type']
            : 'post';

        if (isset($data['title']) && trim((string)$data['title']) !== '') {
            $upd['title'] = (string)$data['title'];
            // pokud se mění title a slug nebyl explicitně zadán, přegeneruj (respektuj unikátnost)
            if (empty($data['slug'])) {
                $upd['slug'] = Slugger::uniqueInPosts($data['title'], $typeForSlug, $id);
            }
        }
        if (isset($data['slug']) && trim((string)$data['slug']) !== '') {
            $upd['slug'] = Slugger::uniqueInPosts((string)$data['slug'], $typeForSlug, $id);
        }
        if (isset($data['status'])) {
            $status = (string)$data['status'];
            if (!in_array($status, ['draft','publish'], true)) {
                throw new \InvalidArgumentException('Neplatný status');
            }
            $upd['status'] = $status;
            if ($status === 'publish' && empty($row['published_at'])) {
                $upd['published_at'] = DateTimeFactory::nowString();
            }
        }
        if (array_key_exists('content',$data)) $upd['content'] = (string)$data['content'];
        if (array_key_exists('thumbnail_id',$data)) $upd['thumbnail_id'] = $data['thumbnail_id'] !== null ? (int)$data['thumbnail_id'] : null;
        if (array_key_exists('comments_allowed',$data)) $upd['comments_allowed'] = (int)$data['comments_allowed'];

        if ($upd === []) return 0;

        $upd['updated_at'] = DateTimeFactory::nowString();
        return $this->posts->update($id, $upd);
    }

    /**
     * @return array<int,array{id:int,title:string,type:string,created_at:?string}>
     */
    public function latestDrafts(string $type = 'post', int $limit = 5): array
    {
        $allowed = ['post', 'page'];
        if (!in_array($type, $allowed, true)) {
            $type = 'post';
        }

        return $this->posts->latestDrafts($type, max(1, $limit));
    }
}
