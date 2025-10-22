<?php
declare(strict_types=1);

namespace Core\Files;

use Core\Text\Slug;

/**
 * Řeší fyzické cesty a veřejné URL.
 * Umožní strukturu uploads/YYYY/MM, slugování názvů, prevenci kolizí.
 */
final class PathResolver
{
    public function __construct(
        private readonly string $baseDir,   // absolutní adresář pro uploady, např. /var/www/site/uploads
        private readonly string $baseUrl    // veřejný URL prefix, např. https://site.tld/uploads
    ) {
        if (!is_dir($this->baseDir) || !is_writable($this->baseDir)) {
            throw new \RuntimeException("Upload base dir not writable: {$this->baseDir}");
        }
    }

    public function baseDir(): string { return rtrim($this->baseDir, '/\\'); }
    public function baseUrl(): string { return rtrim($this->baseUrl, '/'); }

    /**
     * Např. uploads/2025/10
     */
    public function yearMonthPath(?\DateTimeInterface $when = null): string
    {
        $dt = $when ?? new \DateTimeImmutable('now');
        return sprintf('%s/%s/%s', $this->baseDir(), $dt->format('Y'), $dt->format('m'));
    }

    /**
     * Vytvoří adresář (rekurzivně) pokud neexistuje.
     */
    public function ensureDir(string $absDir): void
    {
        if (!is_dir($absDir) && !@mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            throw new \RuntimeException("Cannot create directory: {$absDir}");
        }
    }

    /**
     * Bezpečný název souboru: slug + unikátní suffix + původní přípona.
     */
    public function uniqueFilename(string $originalName, string $dirAbs): string
    {
        [$name, $ext] = $this->splitNameAndExt($originalName);
        $slug = Slug::from($name, 'file');
        $ext  = $this->normalizeExt($ext);

        do {
            $candidate = $slug . '-' . bin2hex(random_bytes(4)) . ($ext ? ".{$ext}" : '');
            $abs = "{$dirAbs}/{$candidate}";
        } while (file_exists($abs));

        return $candidate;
    }

    public function relativeFromAbsolute(string $abs): string
    {
        $base = $this->baseDir() . '/';
        if (!str_starts_with($abs, $base)) {
            throw new \InvalidArgumentException("Path is outside baseDir: {$abs}");
        }
        return ltrim(substr($abs, strlen($base)), '/');
    }

    public function absoluteFromRelative(string $rel): string
    {
        $rel = ltrim($rel, '/\\');
        return $this->baseDir() . '/' . $rel;
    }

    public function publicUrl(string $rel): string
    {
        $rel = ltrim($rel, '/');
        return $this->baseUrl() . '/' . $rel;
    }

    // --- helpers ---

    private function splitNameAndExt(string $filename): array
    {
        $filename = trim($filename);
        $pos = strrpos($filename, '.');
        if ($pos === false) return [$filename, ''];
        return [substr($filename, 0, $pos), substr($filename, $pos + 1)];
    }

    private function normalizeExt(string $ext): string
    {
        $ext = strtolower($ext);
        // Odstraň nebezpečné přípony maskované za dvojtečkou (file.php.jpg)
        $ext = preg_replace('~[^a-z0-9]+~', '', $ext) ?? '';
        return $ext;
    }

}
