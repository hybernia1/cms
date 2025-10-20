<?php
declare(strict_types=1);

namespace Cms\Admin\Domain\Services;

use Cms\Admin\Domain\Result;
use Cms\Admin\Domain\Repositories\PostsRepository;
use Cms\Admin\Domain\Repositories\TermsRepository;
use Cms\Admin\Utils\DateTimeFactory;
use Cms\Admin\Utils\Slugger;
use Core\Database\Init as DB;
use Throwable;

final class PostsCrudService
{
    public function __construct(
        private readonly PostsRepository $posts = new PostsRepository(),
        private readonly PostsService $postsService = new PostsService(),
        private readonly TermsRepository $terms = new TermsRepository(),
        private readonly TermsService $termsService = new TermsService(),
    ) {
    }

    /**
     * @param array{type?:string,status?:string,author?:string,q?:string} $filters
     */
    public function paginate(array $filters, int $page, int $perPage): Result
    {
        try {
            $type = $filters['type'] ?? 'post';
            $paginated = $this->posts->paginate($filters, $page, $perPage);
            $statusCounts = $this->posts->countByStatus($type);

            return Result::success([
                'items'         => $paginated['items'] ?? [],
                'pagination'    => $paginated,
                'status_counts' => $statusCounts,
            ]);
        } catch (Throwable $e) {
            return Result::failure($e->getMessage());
        }
    }

    public function find(int $id): Result
    {
        try {
            $row = $this->posts->find($id);
            if (!$row) {
                return Result::failure('Příspěvek nebyl nalezen.');
            }

            return Result::success($row);
        } catch (Throwable $e) {
            return Result::failure($e->getMessage());
        }
    }

    public function attachedMediaIds(int $postId): Result
    {
        try {
            if ($postId <= 0) {
                return Result::success([]);
            }

            $rows = DB::query()->table('post_media', 'pm')
                ->select(['pm.media_id'])
                ->join('media m', 'pm.media_id', '=', 'm.id')
                ->where('pm.post_id', '=', $postId)
                ->orderBy('m.created_at', 'ASC')
                ->get();

            $ids = [];
            foreach ($rows as $row) {
                $ids[] = (int)($row['media_id'] ?? 0);
            }

            $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));

            return Result::success($ids);
        } catch (Throwable $e) {
            return Result::failure($e->getMessage());
        }
    }

    public function termsData(?int $postId, string $type): Result
    {
        try {
            $byType = ['category' => [], 'tag' => []];
            $selected = ['category' => [], 'tag' => []];

            if ($type !== 'post') {
                return Result::success(['terms' => $byType, 'selected' => $selected]);
            }

            $all = DB::query()->table('terms', 't')
                ->select(['t.id', 't.name', 't.slug', 't.type'])
                ->orderBy('t.type', 'ASC')
                ->orderBy('t.name', 'ASC')
                ->get();

            foreach ($all as $term) {
                $termType = (string)($term['type'] ?? '');
                if (!isset($byType[$termType])) {
                    $byType[$termType] = [];
                }
                $byType[$termType][] = $term;
            }

            if ($postId) {
                $rows = DB::query()->table('post_terms', 'pt')
                    ->select(['pt.term_id', 't.type'])
                    ->join('terms t', 'pt.term_id', '=', 't.id')
                    ->where('pt.post_id', '=', $postId)
                    ->get();
                foreach ($rows as $row) {
                    $termType = (string)($row['type'] ?? '');
                    if (!isset($selected[$termType])) {
                        $selected[$termType] = [];
                    }
                    $selected[$termType][] = (int)($row['term_id'] ?? 0);
                }
            }

            return Result::success([
                'terms'    => $byType,
                'selected' => $selected,
            ]);
        } catch (Throwable $e) {
            return Result::failure($e->getMessage());
        }
    }

    /**
     * @param array{
     *   title:string,
     *   type:string,
     *   status:string,
     *   content:string,
     *   author_id:int,
     *   comments_allowed:int,
     *   thumbnail_id?:int|null,
     *   categories?:array<int>,
     *   tags?:array<int>,
     *   new_categories?:array<int,string>,
     *   new_tags?:array<int,string>,
     *   attached_media?:array<int>
     * } $payload
     */
    public function create(array $payload): Result
    {
        try {
            $type = $payload['type'];
            $termIds = $this->resolveTerms($type, $payload);

            $postId = $this->postsService->create([
                'title'        => $payload['title'],
                'type'         => $type,
                'status'       => $payload['status'],
                'content'      => $payload['content'],
                'author_id'    => $payload['author_id'],
                'thumbnail_id' => $payload['thumbnail_id'] ?? null,
                'terms'        => array_merge($termIds['category'], $termIds['tag']),
            ]);

            if ((int)$payload['comments_allowed'] === 0) {
                $this->postsService->update($postId, ['comments_allowed' => 0]);
            }

            if ($type === 'post') {
                $this->syncTerms($postId, $termIds['category'], $termIds['tag']);
            } else {
                $this->syncTerms($postId, [], []);
            }

            $this->syncPostMedia($postId, $payload['attached_media'] ?? []);

            return Result::success(['id' => $postId]);
        } catch (Throwable $e) {
            return Result::failure($e->getMessage());
        }
    }

    /**
     * @param array{
     *   title?:string,
     *   status?:string,
     *   content?:string,
     *   comments_allowed?:int,
     *   thumbnail_id?:int|null,
     *   slug?:string|null,
     *   type:string,
     *   categories?:array<int>,
     *   tags?:array<int>,
     *   new_categories?:array<int,string>,
     *   new_tags?:array<int,string>,
     *   attached_media?:array<int>
     * } $payload
     */
    public function update(int $id, array $payload): Result
    {
        try {
            $post = $this->posts->find($id);
            if (!$post) {
                return Result::failure('Příspěvek nebyl nalezen.');
            }

            $type = (string)($post['type'] ?? $payload['type']);
            $termIds = $this->resolveTerms($type, $payload);

            $updateData = [
                'title'            => $payload['title'] ?? (string)($post['title'] ?? ''),
                'status'           => $payload['status'] ?? (string)($post['status'] ?? 'draft'),
                'content'          => $payload['content'] ?? (string)($post['content'] ?? ''),
                'comments_allowed' => $payload['comments_allowed'] ?? (int)($post['comments_allowed'] ?? 0),
            ];

            if (array_key_exists('thumbnail_id', $payload)) {
                $updateData['thumbnail_id'] = $payload['thumbnail_id'];
            }

        if (isset($payload['slug']) && $payload['slug'] !== null && trim((string)$payload['slug']) !== '') {
            $updateData['slug'] = Slugger::uniqueInPosts((string)$payload['slug'], $type, $id);
        }

        $this->postsService->update($id, $updateData);

            if ($type === 'post') {
                $this->syncTerms($id, $termIds['category'], $termIds['tag']);
            } else {
                $this->syncTerms($id, [], []);
            }

            $this->syncPostMedia($id, $payload['attached_media'] ?? []);

            return Result::success(['id' => $id]);
        } catch (Throwable $e) {
            return Result::failure($e->getMessage());
        }
    }

    /**
     * @param array<int> $ids
     */
    public function bulk(string $type, string $action, array $ids): Result
    {
        try {
            if ($ids === []) {
                return Result::failure('Žádné platné položky pro hromadnou akci.');
            }

            $existing = DB::query()->table('posts')
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
                return Result::failure('Žádné platné položky pro hromadnou akci.');
            }

            switch ($action) {
                case 'publish':
                case 'draft':
                    DB::query()->table('posts')
                        ->update(['status' => $action])
                        ->whereIn('id', $targetIds)
                        ->execute();
                    $message = $action === 'publish'
                        ? 'Položky byly publikovány.'
                        : 'Položky byly přepnuty na koncept.';
                    break;

                case 'delete':
                    DB::query()->table('post_terms')
                        ->delete()
                        ->whereIn('post_id', $targetIds)
                        ->execute();
                    DB::query()->table('post_media')
                        ->delete()
                        ->whereIn('post_id', $targetIds)
                        ->execute();
                    DB::query()->table('comments')
                        ->delete()
                        ->whereIn('post_id', $targetIds)
                        ->execute();
                    DB::query()->table('posts')
                        ->delete()
                        ->whereIn('id', $targetIds)
                        ->execute();
                    $message = 'Položky byly odstraněny.';
                    break;

                default:
                    return Result::failure('Neznámá hromadná akce.');
            }

            return Result::success([
                'count'   => count($targetIds),
                'message' => $message,
            ], $message);
        } catch (Throwable $e) {
            return Result::failure($e->getMessage());
        }
    }

    public function delete(int $id): Result
    {
        try {
            $post = $this->posts->find($id);
            if (!$post) {
                return Result::failure('Příspěvek nenalezen.');
            }

            DB::query()->table('post_terms')->delete()->where('post_id', '=', $id)->execute();
            DB::query()->table('post_media')->delete()->where('post_id', '=', $id)->execute();
            DB::query()->table('comments')->delete()->where('post_id', '=', $id)->execute();
            $this->posts->delete($id);

            return Result::success(['type' => (string)($post['type'] ?? 'post')]);
        } catch (Throwable $e) {
            return Result::failure($e->getMessage());
        }
    }

    public function toggleStatus(int $id): Result
    {
        try {
            $post = $this->posts->find($id);
            if (!$post) {
                return Result::failure('Příspěvek nenalezen.');
            }

            $current = (string)($post['status'] ?? 'draft');
            $new = $current === 'publish' ? 'draft' : 'publish';
            $this->postsService->update($id, ['status' => $new]);

            return Result::success([
                'type'       => (string)($post['type'] ?? 'post'),
                'new_status' => $new,
            ]);
        } catch (Throwable $e) {
            return Result::failure($e->getMessage());
        }
    }

    private function normalizeType(string $type): string
    {
        $allowed = ['post', 'page'];
        return in_array($type, $allowed, true) ? $type : 'post';
    }

    private function normalizeStatus(string $status): string
    {
        $allowed = ['draft', 'publish'];
        return in_array($status, $allowed, true) ? $status : 'draft';
    }

    /**
     * @param array{
     *   id?:int,
     *   title:string,
     *   content:string,
     *   status:string,
     *   type:string,
     *   comments_allowed:int,
     *   selected_thumbnail_id?:int,
     *   remove_thumbnail?:bool,
     *   slug?:string|null,
     *   categories?:array<int>,
     *   tags?:array<int>,
     *   new_categories?:array<int,string>,
     *   new_tags?:array<int,string>,
     *   attached_media?:array<int>,
     *   author_id:int
     * } $payload
     */
    public function autosave(array $payload): Result
    {
        try {
            $requestedType = $this->normalizeType($payload['type'] ?? 'post');
            $id = (int)($payload['id'] ?? 0);
            $title = (string)$payload['title'];
            $content = (string)$payload['content'];
            $status = $this->normalizeStatus($payload['status'] ?? 'draft');
            $commentsAllowed = $requestedType === 'post' ? (int)$payload['comments_allowed'] : 0;
            $selectedThumbId = isset($payload['selected_thumbnail_id']) ? (int)$payload['selected_thumbnail_id'] : 0;
            $removeThumb = !empty($payload['remove_thumbnail']);
            $slugInput = isset($payload['slug']) ? (string)$payload['slug'] : null;

            $termIds = $this->resolveTerms($requestedType, $payload);
            $attachedMedia = $this->normalizeIds($payload['attached_media'] ?? []);

            $now = DateTimeFactory::nowString();
            $type = $requestedType;
            $currentStatus = $status;
            $slug = $slugInput;

            if ($id > 0) {
                $existing = $this->posts->find($id);
                if (!$existing) {
                    throw new \RuntimeException('Položku se nepodařilo načíst.');
                }

                $rowType = $this->normalizeType((string)($existing['type'] ?? $requestedType));
                $type = $rowType;
                if ($type !== 'post') {
                    $commentsAllowed = 0;
                }

                $updates = [
                    'title'            => $title,
                    'content'          => $content,
                    'comments_allowed' => $commentsAllowed,
                    'updated_at'       => $now,
                ];

                if ($removeThumb) {
                    $updates['thumbnail_id'] = null;
                } elseif ($selectedThumbId > 0) {
                    $updates['thumbnail_id'] = $selectedThumbId;
                }

                if ($slugInput !== null && trim($slugInput) !== '') {
                    $slug = Slugger::uniqueInPosts($slugInput, $type, $id);
                    $updates['slug'] = $slug;
                }

                $currentStatus = (string)($existing['status'] ?? $currentStatus);
                if ($status !== $currentStatus) {
                    $updates['status'] = $status;
                    $currentStatus = $status;
                }

                $this->posts->update($id, $updates);
            } else {
                $hasMeaningful = $this->hasMeaningfulDraft($title, $content, $attachedMedia, $selectedThumbId, $commentsAllowed, $termIds, $status);
                if (!$hasMeaningful) {
                    return Result::success([
                        'created' => false,
                    ]);
                }

                $type = $requestedType;
                $slugSource = $title !== '' ? $title : ('koncept-' . bin2hex(random_bytes(3)));
                $slug = Slugger::uniqueInPosts($slugSource, $type);

                if ($type !== 'post') {
                    $commentsAllowed = 0;
                }

                $data = [
                    'title'            => $title,
                    'slug'             => $slug,
                    'type'             => $type,
                    'status'           => 'draft',
                    'content'          => $content,
                    'author_id'        => (int)$payload['author_id'],
                    'thumbnail_id'     => $removeThumb ? null : ($selectedThumbId > 0 ? $selectedThumbId : null),
                    'comments_allowed' => $commentsAllowed,
                    'published_at'     => null,
                    'created_at'       => $now,
                    'updated_at'       => null,
                ];

                $id = $this->posts->create($data);
                $currentStatus = 'draft';
            }

            if ($id <= 0) {
                throw new \RuntimeException('Nepodařilo se uložit koncept.');
            }

            if ($type === 'post') {
                $this->syncTerms($id, $termIds['category'], $termIds['tag']);
            } else {
                $this->syncTerms($id, [], []);
            }
            $this->syncPostMedia($id, $attachedMedia);

            return Result::success([
                'post_id'      => $id,
                'status'       => $currentStatus,
                'type'         => $type,
                'slug'         => $slug,
                'created'      => true,
            ]);
        } catch (Throwable $e) {
            return Result::failure($e->getMessage());
        }
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{category:array<int>,tag:array<int>}
     */
    private function resolveTerms(string $type, array $payload): array
    {
        $categories = $this->normalizeIds($payload['categories'] ?? []);
        $tags = $this->normalizeIds($payload['tags'] ?? []);

        if ($type !== 'post') {
            return ['category' => [], 'tag' => []];
        }

        $categories = array_merge($categories, $this->createMissingTerms($payload['new_categories'] ?? [], 'category'));
        $tags = array_merge($tags, $this->createMissingTerms($payload['new_tags'] ?? [], 'tag'));

        return [
            'category' => array_values(array_unique($categories)),
            'tag'      => array_values(array_unique($tags)),
        ];
    }

    /**
     * @param array{category:array<int>,tag:array<int>} $termIds
     */
    private function hasMeaningfulDraft(string $title, string $content, array $attachedMedia, int $selectedThumbId, int $commentsAllowed, array $termIds, string $status): bool
    {
        if (trim($title) !== '') {
            return true;
        }

        $contentPlain = trim(strip_tags($content));
        if ($contentPlain !== '') {
            return true;
        }

        if ($attachedMedia !== []) {
            return true;
        }

        if ($selectedThumbId > 0) {
            return true;
        }

        if ($commentsAllowed === 0) {
            return true;
        }

        if ($termIds['category'] !== [] || $termIds['tag'] !== []) {
            return true;
        }

        if ($status !== 'draft') {
            return true;
        }

        return false;
    }

    /**
     * @param array<int|string> $values
     * @return array<int>
     */
    private function normalizeIds(array $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            $id = (int)$value;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /**
     * @param array<int,string> $names
     * @return array<int>
     */
    private function createMissingTerms(array $names, string $type): array
    {
        $ids = [];
        foreach ($names as $name) {
            $trimmed = trim((string)$name);
            if ($trimmed === '') {
                continue;
            }
            $existing = $this->terms->findByNameAndType($trimmed, $type);
            if ($existing) {
                $ids[] = (int)$existing['id'];
                continue;
            }
            $ids[] = $this->termsService->create($trimmed, $type);
        }

        return $ids;
    }

    /**
     * @param array<int> $categoryIds
     * @param array<int> $tagIds
     */
    private function syncTerms(int $postId, array $categoryIds, array $tagIds): void
    {
        $cat = array_values(array_unique(array_map('intval', $categoryIds)));
        $tag = array_values(array_unique(array_map('intval', $tagIds)));

        DB::query()->table('post_terms')->delete()->where('post_id', '=', $postId)->execute();

        $ins = DB::query()->table('post_terms')->insert(['post_id', 'term_id']);
        $hasRows = false;
        foreach (array_merge($cat, $tag) as $termId) {
            if ($termId <= 0) {
                continue;
            }
            $ins->values([$postId, $termId]);
            $hasRows = true;
        }
        if ($hasRows) {
            $ins->execute();
        }
    }

    /**
     * @param array<int> $mediaIds
     */
    private function syncPostMedia(int $postId, array $mediaIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $mediaIds), static fn (int $id): bool => $id > 0)));

        DB::query()->table('post_media')
            ->delete()
            ->where('post_id', '=', $postId)
            ->execute();

        if ($ids === []) {
            return;
        }

        $ins = DB::query()->table('post_media')->insert(['post_id', 'media_id']);
        $hasRows = false;
        foreach ($ids as $mediaId) {
            $ins->values([$postId, $mediaId]);
            $hasRows = true;
        }
        if ($hasRows) {
            $ins->execute();
        }
    }
}
