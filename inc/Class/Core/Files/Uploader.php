<?php
declare(strict_types=1);

namespace Core\Files;

/**
 * Zpracuje jeden uploadovaný soubor (pole z $_FILES['field']).
 * Validace: velikost, MIME (finfo), přípona; cílová cesta Y/m; unikátní název.
 */
final class Uploader
{
    private PathResolver $paths;
    private array $allowedMimes;
    private int $maxBytes;

    /**
     * @param PathResolver   $paths        resolver cest/URL (uploads base)
     * @param array|null     $allowedMimes whitelist MIME; když null, použije se Mime::defaultWhitelist()
     * @param int            $maxBytes     maximální velikost v bajtech (default 10 MB)
     */
    public function __construct(
        PathResolver $paths,
        ?array $allowedMimes = null,
        int $maxBytes = 10000000 // 10 MB
    ) {
        $this->paths        = $paths;
        $this->allowedMimes = $allowedMimes ?? Mime::defaultWhitelist();
        $this->maxBytes     = $maxBytes;
    }

    /**
     * @param array{ name:string, type:string, tmp_name:string, error:int, size:int } $file
     * @param ?string $subdir Relativní podsložka uvnitř uploads (např. 'avatars')
     * @param bool    $useDateSubdir Když true, vytvoří YYYY/MM strukturu; jinak ukládá přímo do baseDir
     * @return array{
     *   relative:string, url:string, name:string, size:int, mime:string,
     *   width?:int, height?:int
     * }
     */
    public function handle(array $file, ?string $subdir = null, bool $useDateSubdir = true): array
    {
        $this->assertNoUploadError((int)($file['error'] ?? UPLOAD_ERR_NO_FILE));

        $tmp  = (string)$file['tmp_name'];
        $name = (string)$file['name'];
        $size = (int)$file['size'];

        if (!is_uploaded_file($tmp)) {
            throw new \RuntimeException('Invalid upload (not an uploaded file).');
        }
        if ($size <= 0 || $size > $this->maxBytes) {
            throw new \RuntimeException('File size out of allowed range.');
        }

        // cílový adresář: uploads/[Y/m][/subdir]
        $targetDir = $useDateSubdir
            ? $this->paths->yearMonthPath()
            : $this->paths->baseDir();
        if ($subdir) {
            $subdir   = trim($subdir, '/\\');
            $targetDir .= '/' . $subdir;
        }
        $this->paths->ensureDir($targetDir);

        // bezpečný unikátní název
        $finalName = $this->paths->uniqueFilename($name, $targetDir);
        $abs       = "{$targetDir}/{$finalName}";

        // přesun
        if (!@move_uploaded_file($tmp, $abs)) {
            throw new \RuntimeException('Cannot move uploaded file.');
        }
        @chmod($abs, 0644);

        // MIME + whitelist
        $mime = Mime::detect($abs);
        if (!Mime::isAllowed($mime, $this->allowedMimes)) {
            @unlink($abs);
            throw new \RuntimeException("MIME not allowed: {$mime}");
        }

        // rozměry obrázku (volitelné)
        $meta = [];
        if (str_starts_with($mime, 'image/')) {
            $dim = @getimagesize($abs);
            if ($dim) {
                $meta['width']  = (int)$dim[0];
                $meta['height'] = (int)$dim[1];
            }
        }

        $rel = $this->paths->relativeFromAbsolute($abs);

        return array_merge([
            'relative' => $rel,
            'url'      => $this->paths->publicUrl($rel),
            'name'     => $finalName,
            'size'     => $size,
            'mime'     => $mime,
        ], $meta);
    }

    private function assertNoUploadError(int $code): void
    {
        if ($code === UPLOAD_ERR_OK) return;

        $map = [
            UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds upload_max_filesize.',
            UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds MAX_FILE_SIZE.',
            UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
        ];

        $msg = $map[$code] ?? "Upload error ({$code}).";
        throw new \RuntimeException($msg);
    }
}
