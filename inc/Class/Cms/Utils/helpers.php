<?php
declare(strict_types=1);

if (!function_exists('cms_html_escape')) {
    /**
     * Escape value for safe HTML output.
     */
    function cms_html_escape(\Stringable|string|int|float|bool|null $value, int $flags = ENT_QUOTES | ENT_SUBSTITUTE, string $encoding = 'UTF-8', bool $doubleEncode = true): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        if ($value instanceof \Stringable) {
            $value = (string) $value;
        }

        return htmlspecialchars((string) $value, $flags, $encoding, $doubleEncode);
    }
}

if (!function_exists('e')) {
    /**
     * Short alias for {@see cms_html_escape()}.
     */
    function e(\Stringable|string|int|float|bool|null $value, int $flags = ENT_QUOTES | ENT_SUBSTITUTE, string $encoding = 'UTF-8', bool $doubleEncode = true): string
    {
        return cms_html_escape($value, $flags, $encoding, $doubleEncode);
    }
}
