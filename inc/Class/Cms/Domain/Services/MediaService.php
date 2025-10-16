<?php
declare(strict_types=1);

namespace Cms\Domain\Services;

use Core\Files\PathResolver;
use Core\Files\Uploader;
use Cms\Domain\Repositories\MediaRepository;
use Cms\Utils\DateTimeFactory;

final class MediaService
{
    public function __construct(
        private readonly MediaRepository $repo = new MediaRepository()
    ) {}

    /**
     * Zpracuje upload přes Core\Files\Uploader a vloží záznam do media.
     * @param array $file  jeden prvek z $_FILES['field']
     * @return array{ id:int, url:string, mime:string }
     */
    public function uploadAndCreate(array $file, int $userId, PathResolver $paths, ?string $subdir = 'uploads'): array
    {
        $uploader = new Uploader($paths);
        $info = $uploader->handle($file, $subdir);

        $id = $this->repo->create([
            'user_id'    => $userId,
            'type'       => str_starts_with($info['mime'], 'image/') ? 'image' : 'file',
            'mime'       => $info['mime'],
            'url'        => $info['url'],
            'rel_path'   => $info['relative'] ?? null,
            'meta'       => isset($info['width']) ? json_encode(['w'=>$info['width'],'h'=>$info['height']], JSON_UNESCAPED_UNICODE) : null,
            'created_at' => DateTimeFactory::nowString(),
        ]);

        return ['id'=>$id, 'url'=>$info['url'], 'mime'=>$info['mime']];
    }
}
