<?php
declare(strict_types=1);

namespace Core\Files;

/**
 * Bezpečná detekce MIME: preferuj finfo nad příponou.
 * Umí jednoduchý whitelist.
 */
final class Mime
{
    public static function detect(string $absFile): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($absFile) ?: 'application/octet-stream';
        return $mime;
    }

    public static function isAllowed(string $mime, array $allowed): bool
    {
        // Podpora skupinových patternů: image/*, text/* atd.
        foreach ($allowed as $pattern) {
            if (str_ends_with($pattern, '/*')) {
                $prefix = substr($pattern, 0, -2);
                if (str_starts_with($mime, $prefix . '/')) {
                    return true;
                }
            } elseif (strcasecmp($mime, $pattern) === 0) {
                return true;
            }
        }
        return false;
    }

    public static function defaultWhitelist(): array
    {
        return [
            'image/jpeg','image/png','image/gif','image/webp','image/avif',
            'application/pdf',
            'text/plain',
            'application/json',
            // případně doplň
        ];
    }
}
