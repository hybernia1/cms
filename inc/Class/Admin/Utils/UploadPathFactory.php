<?php
declare(strict_types=1);

namespace Cms\Admin\Utils;

use Core\Files\PathResolver;

final class UploadPathFactory
{
    public static function forUploads(?string $uploadsDir = null): PathResolver
    {
        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $webBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        $baseUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($webBase === '' ? '' : $webBase) . '/uploads';

        return new PathResolver(
            baseDir: $uploadsDir ?? dirname(__DIR__, 4) . '/uploads',
            baseUrl: $baseUrl,
        );
    }
}
