<?php
declare(strict_types=1);

namespace Cms\Front\View;

use RuntimeException;

final class MissingThemeException extends RuntimeException
{
    public static function forEmptySlug(string $themesDir): self
    {
        return new self(sprintf("Theme slug is empty. Expected directory inside %s", $themesDir));
    }

    public static function forSlug(string $slug, string $path): self
    {
        return new self(sprintf("Theme '%s' is missing templates directory at %s", $slug, $path));
    }
}
