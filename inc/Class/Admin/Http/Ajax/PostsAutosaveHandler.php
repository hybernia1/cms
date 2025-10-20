<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax;

use Cms\Admin\Auth\AuthService;
use Cms\Admin\Domain\Result;
use Cms\Admin\Domain\Services\PostsCrudService;
use Cms\Admin\Http\AjaxResponse;

final class PostsAutosaveHandler
{
    private PostsCrudService $postsCrud;
    private AuthService $auth;

    public function __construct(?PostsCrudService $postsCrud = null, ?AuthService $auth = null)
    {
        $this->postsCrud = $postsCrud ?? new PostsCrudService();
        $this->auth = $auth ?? new AuthService();
    }

    public function __invoke(): AjaxResponse
    {
        $user = $this->auth->user();
        if (!$user) {
            return AjaxResponse::error('Nejste přihlášeni.', 401);
        }

        $type = $this->requestedType();
        $payload = $this->buildPayload($type, (int)($user['id'] ?? 0));

        try {
            $result = $this->postsCrud->autosave($payload);
        } catch (\Throwable $exception) {
            $message = trim((string)$exception->getMessage());
            if ($message === '') {
                $message = 'Automatické uložení selhalo.';
            }

            return AjaxResponse::error($message, 500);
        }

        if ($result->isFailure()) {
            return AjaxResponse::error($result->errors(), 422);
        }

        $data = $this->normalizeResultData($result);
        if ($data['created'] === false) {
            return AjaxResponse::success([
                'created' => false,
            ]);
        }

        $status = $data['status'];
        $statusLabel = $this->statusLabel($status);
        $postId = $data['post_id'];
        $type = $data['type'];

        $response = [
            'message'     => 'Automaticky uloženo v ' . date('H:i:s'),
            'postId'      => $postId,
            'status'      => $status,
            'statusLabel' => $statusLabel,
            'actionUrl'   => 'admin.php?' . http_build_query([
                'r'    => 'posts',
                'a'    => 'edit',
                'id'   => $postId,
                'type' => $type,
            ]),
            'type'        => $type,
            'created'     => true,
        ];

        if ($data['slug'] !== null) {
            $response['slug'] = $data['slug'];
        }

        return AjaxResponse::success($response);
    }

    private function requestedType(): string
    {
        $raw = $_REQUEST['type'] ?? 'post';
        $type = is_scalar($raw) ? (string)$raw : 'post';

        return in_array($type, ['post', 'page'], true) ? $type : 'post';
    }

    private function buildPayload(string $type, int $authorId): array
    {
        $rawId = $_POST['id'] ?? $_POST['post_id'] ?? null;
        $id = is_scalar($rawId) ? (int)$rawId : 0;

        $payload = [
            'id'                   => $id,
            'title'                => (string)($_POST['title'] ?? ''),
            'content'              => (string)($_POST['content'] ?? ''),
            'status'               => (string)($_POST['status'] ?? 'draft'),
            'type'                 => $type,
            'comments_allowed'     => $type === 'post' ? (isset($_POST['comments_allowed']) ? 1 : 0) : 0,
            'selected_thumbnail_id'=> isset($_POST['selected_thumbnail_id']) ? (int)$_POST['selected_thumbnail_id'] : 0,
            'remove_thumbnail'     => isset($_POST['remove_thumbnail']) && (int)$_POST['remove_thumbnail'] === 1,
            'slug'                 => isset($_POST['slug']) ? (string)$_POST['slug'] : null,
            'categories'           => $type === 'post' ? $this->normalizeArray($_POST['categories'] ?? []) : [],
            'tags'                 => $type === 'post' ? $this->normalizeArray($_POST['tags'] ?? []) : [],
            'new_categories'       => $type === 'post' ? $this->parseNewTerms($_POST['new_categories'] ?? []) : [],
            'new_tags'             => $type === 'post' ? $this->parseNewTerms($_POST['new_tags'] ?? []) : [],
            'attached_media'       => $this->parseAttachedMedia($_POST['attached_media'] ?? ''),
            'author_id'            => $authorId,
        ];

        return $payload;
    }

    /**
     * @param mixed $input
     * @return array<int>
     */
    private function normalizeArray(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }

        $values = [];
        foreach ($input as $value) {
            $values[] = $value;
        }

        return $values;
    }

    /**
     * @param mixed $input
     * @return array<int,string>
     */
    private function parseNewTerms(mixed $input): array
    {
        if (is_string($input)) {
            $parts = preg_split('/[,\n]+/', $input);
            $input = $parts === false ? [] : $parts;
        }

        if (!is_array($input)) {
            return [];
        }

        $out = [];
        foreach ($input as $value) {
            $name = trim((string)$value);
            if ($name !== '') {
                $out[] = $name;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param mixed $raw
     * @return array<int>
     */
    private function parseAttachedMedia(mixed $raw): array
    {
        if (is_array($raw)) {
            $values = $raw;
        } elseif (is_string($raw)) {
            $raw = trim($raw);
            if ($raw === '') {
                return [];
            }
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $values = $decoded;
            } else {
                $values = array_map('trim', explode(',', $raw));
            }
        } else {
            return [];
        }

        $ids = [];
        foreach ($values as $value) {
            $id = (int)$value;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function normalizeResultData(Result $result): array
    {
        $data = $result->data();
        if (!is_array($data)) {
            $data = [];
        }

        $id = isset($data['post_id']) ? (int)$data['post_id'] : (int)($_POST['id'] ?? $_POST['post_id'] ?? 0);
        $type = isset($data['type']) ? (string)$data['type'] : $this->requestedType();
        $status = isset($data['status']) ? (string)$data['status'] : (string)($_POST['status'] ?? 'draft');

        return [
            'post_id' => $id,
            'type'    => in_array($type, ['post', 'page'], true) ? $type : 'post',
            'status'  => $status,
            'slug'    => isset($data['slug']) ? (string)$data['slug'] : null,
            'created' => (bool)($data['created'] ?? true),
        ];
    }

    private function statusLabel(string $status): string
    {
        $labels = [
            'draft'   => 'Koncept',
            'publish' => 'Publikováno',
        ];

        $trimmed = trim(strtolower($status));
        if ($trimmed === '') {
            $trimmed = 'draft';
        }

        return $labels[$trimmed] ?? ucfirst($trimmed);
    }
}
