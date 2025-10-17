<?php
declare(strict_types=1);

namespace Cms\Domain\Services;

use Core\Files\PathResolver;
use Core\Files\Uploader;
use Cms\Domain\Repositories\MediaRepository;
use Cms\Utils\DateTimeFactory;
use Cms\Settings\CmsSettings;

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
    public function uploadAndCreate(
        array $file,
        int $userId,
        PathResolver $paths,
        ?string $subdir = 'uploads',
        ?int $postId = null
    ): array
    {
        $uploader = new Uploader($paths);
        $info = $uploader->handle($file, $subdir);

        $meta = $this->buildMeta($info, $paths);

        $id = $this->repo->create([
            'user_id'    => $userId,
            'type'       => str_starts_with($info['mime'], 'image/') ? 'image' : 'file',
            'mime'       => $info['mime'],
            'url'        => $info['url'],
            'rel_path'   => $info['relative'] ?? null,
            'meta'       => $meta !== null ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'created_at' => DateTimeFactory::nowString(),
        ]);

        if ($postId !== null && $postId > 0) {
            $this->repo->attachToPost($postId, $id);
        }

        return ['id'=>$id, 'url'=>$info['url'], 'mime'=>$info['mime']];
    }

    public function optimizeWebp(int $mediaId, PathResolver $paths): bool
    {
        $row = $this->repo->find($mediaId);
        if (!$row) {
            throw new \RuntimeException('Soubor nebyl nalezen.');
        }

        $mime = (string)($row['mime'] ?? '');
        if (!str_starts_with($mime, 'image/')) {
            throw new \RuntimeException('Optimalizace je dostupná pouze pro obrázky.');
        }

        $settings = new CmsSettings();
        if (!$settings->webpEnabled()) {
            throw new \RuntimeException('WebP konverze je v nastavení vypnutá.');
        }

        $meta = $this->decodeMeta($row['meta'] ?? null);
        $existingWebp = isset($meta['webp']) && is_string($meta['webp']) && $meta['webp'] !== ''
            ? $meta['webp']
            : null;

        if ($existingWebp !== null && $this->webpFileExists($existingWebp, $paths)) {
            return false;
        }

        $relative = (string)($row['rel_path'] ?? '');
        if ($relative === '') {
            throw new \RuntimeException('Chybí relativní cesta k souboru.');
        }

        $info = [
            'relative' => $relative,
            'mime'     => $mime,
        ];

        $webpRel = $this->maybeCreateWebp($info, $paths);
        if ($webpRel === null) {
            throw new \RuntimeException('Nepodařilo se vytvořit WebP variantu.');
        }

        $meta['webp'] = $webpRel;

        if (!isset($meta['w']) || !isset($meta['h'])) {
            try {
                $abs = $paths->absoluteFromRelative($relative);
                if (is_file($abs)) {
                    $size = @getimagesize($abs);
                    if ($size) {
                        $meta['w'] = (int)$size[0];
                        $meta['h'] = (int)$size[1];
                    }
                }
            } catch (\Throwable) {
                // Ignorujeme, rozměry nejsou kritické pro úspěch optimalizace.
            }
        }

        $metaJson = $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $this->repo->update($mediaId, ['meta' => $metaJson]);

        return true;
    }

    /**
     * @param array{relative?:string,width?:int,height?:int,mime:string} $info
     */
    private function buildMeta(array $info, PathResolver $paths): ?array
    {
        if (!str_starts_with($info['mime'], 'image/')) {
            return null;
        }

        $meta = [];
        if (isset($info['width'])) {
            $meta['w'] = (int)$info['width'];
        }
        if (isset($info['height'])) {
            $meta['h'] = (int)$info['height'];
        }

        $webpRel = $this->maybeCreateWebp($info, $paths);
        if ($webpRel !== null) {
            $meta['webp'] = $webpRel;
        }

        return $meta !== [] ? $meta : null;
    }

    /**
     * @param array{relative?:string,mime:string} $info
     */
    private function maybeCreateWebp(array $info, PathResolver $paths): ?string
    {
        if (empty($info['relative'])) {
            return null;
        }

        if (!function_exists('imagewebp')) {
            return null;
        }

        $settings = new CmsSettings();
        if (!$settings->webpEnabled()) {
            return null;
        }

        $relative = ltrim($info['relative'], '/');
        $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        if ($ext === 'webp') {
            return $relative;
        }

        $absSource = $paths->absoluteFromRelative($relative);
        if (!is_file($absSource)) {
            return null;
        }

        if (in_array($info['mime'], ['image/gif', 'image/x-gif'], true) && $this->isAnimatedGif($absSource)) {
            return null; // zachováme animace bez konverze
        }

        $image = $this->createImageResource($absSource, $info['mime']);
        if (!$image) {
            return null;
        }

        $targetRel = $this->webpRelativePath($relative);
        $targetAbs = $paths->absoluteFromRelative($targetRel);

        // ensure directory exists (should already), but guard against unexpected structure
        $dir = dirname($targetAbs);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            imagedestroy($image);
            return null;
        }

        $quality = $this->mapWebpQuality($settings->webpCompression());

        $result = imagewebp($image, $targetAbs, $quality);
        imagedestroy($image);

        if (!$result) {
            @unlink($targetAbs);
            return null;
        }

        @chmod($targetAbs, 0644);

        return $paths->relativeFromAbsolute($targetAbs);
    }

    private function mapWebpQuality(string $compression): int
    {
        return match ($compression) {
            'high'   => 60,
            'low'    => 90,
            default  => 75,
        };
    }

    private function webpRelativePath(string $relative): string
    {
        $relative = ltrim($relative, '/');
        $dotPos = strrpos($relative, '.');
        if ($dotPos === false) {
            return $relative . '.webp';
        }
        return substr($relative, 0, $dotPos) . '.webp';
    }

    private function isAnimatedGif(string $path): bool
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            return false;
        }
        return str_contains($contents, 'NETSCAPE2.0');
    }

    private function createImageResource(string $absPath, string $mime)
    {
        $mime = strtolower($mime);
        switch ($mime) {
            case 'image/jpeg':
            case 'image/pjpeg':
                return @imagecreatefromjpeg($absPath);
            case 'image/png':
                $img = @imagecreatefrompng($absPath);
                if ($img) {
                    if (function_exists('imagepalettetotruecolor')) {
                        @imagepalettetotruecolor($img);
                    }
                    if (function_exists('imagealphablending')) {
                        @imagealphablending($img, true);
                    }
                    if (function_exists('imagesavealpha')) {
                        @imagesavealpha($img, true);
                    }
                }
                return $img;
            case 'image/gif':
                $img = @imagecreatefromgif($absPath);
                if ($img) {
                    $trueColor = imagecreatetruecolor(imagesx($img), imagesy($img));
                    if ($trueColor) {
                        imagealphablending($trueColor, false);
                        imagesavealpha($trueColor, true);
                        $transparent = imagecolorallocatealpha($trueColor, 0, 0, 0, 127);
                        imagefill($trueColor, 0, 0, $transparent);
                        imagecopyresampled($trueColor, $img, 0, 0, 0, 0, imagesx($img), imagesy($img), imagesx($img), imagesy($img));
                        imagedestroy($img);
                        $img = $trueColor;
                    }
                }
                return $img;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    return @imagecreatefromwebp($absPath);
                }
                // fallback to generic loader
                // no break intentionally
            default:
                // Attempt generic loader for formats supported by GD (e.g., BMP)
                if (function_exists('imagecreatefromstring')) {
                    $contents = @file_get_contents($absPath);
                    if ($contents !== false) {
                        return @imagecreatefromstring($contents);
                    }
                }
                return null;
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeMeta(mixed $raw): array
    {
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function webpFileExists(string $relative, PathResolver $paths): bool
    {
        try {
            $abs = $paths->absoluteFromRelative($relative);
        } catch (\Throwable) {
            return false;
        }

        return is_file($abs);
    }
}
