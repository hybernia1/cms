<?php
declare(strict_types=1);

namespace Core\Files;

/**
 * Základní FS operace + převod path<->url.
 */
final class Storage
{
    public function __construct(private readonly PathResolver $paths) {}

    public function exists(string $rel): bool
    {
        return is_file($this->paths->absoluteFromRelative($rel));
    }

    public function delete(string $rel): bool
    {
        $abs = $this->paths->absoluteFromRelative($rel);
        return is_file($abs) ? @unlink($abs) : true;
    }

    public function filesize(string $rel): int
    {
        $abs = $this->paths->absoluteFromRelative($rel);
        $size = @filesize($abs);
        return $size === false ? 0 : (int)$size;
    }

    public function read(string $rel): string
    {
        $abs = $this->paths->absoluteFromRelative($rel);
        $data = @file_get_contents($abs);
        if ($data === false) {
            throw new \RuntimeException("Cannot read file: {$rel}");
        }
        return $data;
    }

    public function write(string $rel, string $contents): void
    {
        $abs = $this->paths->absoluteFromRelative($rel);
        $dir = \dirname($abs);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create dir for write: {$dir}");
        }
        if (@file_put_contents($abs, $contents, LOCK_EX) === false) {
            throw new \RuntimeException("Cannot write file: {$rel}");
        }
    }

    public function path(string $rel): string { return $this->paths->absoluteFromRelative($rel); }
    public function url(string $rel): string  { return $this->paths->publicUrl($rel); }
}
