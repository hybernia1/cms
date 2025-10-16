<?php
declare(strict_types=1);

namespace Cms\Domain\Services;

use Cms\Domain\Repositories\CommentsRepository;
use Cms\Utils\DateTimeFactory;
use Cms\Validation\Validator;

final class CommentsService
{
    public function __construct(private readonly CommentsRepository $repo = new CommentsRepository()) {}

    /**
     * Vytvoří komentář (registrovaný i anonymní).
     * @param array{
     *   post_id:int, user_id?:int|null, parent_id?:int|null,
     *   author_name?:string|null, author_email?:string|null,
     *   content:string, status?:string
     * } $data
     */
    public function create(array $data): int
    {
        $data['status'] = $data['status'] ?? 'published';

        $v = (new Validator())
            ->require($data, 'post_id')
            ->require($data, 'content')
            ->enum($data, 'status', ['draft','published','spam','trash'])
            ->email($data, 'author_email');

        if (!$v->ok()) {
            throw new \InvalidArgumentException(json_encode($v->errors(), JSON_UNESCAPED_UNICODE));
        }

        return $this->repo->create([
            'post_id'      => (int)$data['post_id'],
            'user_id'      => isset($data['user_id']) ? (int)$data['user_id'] : null,
            'parent_id'    => isset($data['parent_id']) ? (int)$data['parent_id'] : null,
            'author_name'  => $data['author_name'] ?? null,
            'author_email' => $data['author_email'] ?? null,
            'content'      => (string)$data['content'],
            'status'       => (string)$data['status'],
            'ip'           => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua'           => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at'   => DateTimeFactory::nowString(),
        ]);
    }
}
