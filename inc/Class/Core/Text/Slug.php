<?php
declare(strict_types=1);

namespace Core\Text;

final class Slug
{
    private const DEFAULT_FALLBACK = 'item';

    /**
     * Create a URL/file-system safe slug from the provided value.
     */
    public static function from(string $value, string $fallback = self::DEFAULT_FALLBACK): string
    {
        $value = trim($value);
        if ($value === '') {
            return $fallback;
        }

        $value = mb_strtolower($value, 'UTF-8');
        $value = self::transliterate($value);
        $value = preg_replace('~[^a-z0-9]+~', '-', $value) ?? '';
        $value = trim($value, '-');
        $value = preg_replace('~[^-\w]+~', '', $value) ?? '';

        return $value !== '' ? $value : $fallback;
    }

    private static function transliterate(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        if (class_exists(\Transliterator::class)) {
            $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII', $value);
            if (is_string($transliterated) && $transliterated !== '') {
                return $transliterated;
            }
        }

        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($transliterated) && $transliterated !== '') {
            return $transliterated;
        }

        return strtr($value, self::fallbackTable());
    }

    private static function fallbackTable(): array
    {
        return [
            'á' => 'a', 'ä' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'ë' => 'e',
            'í' => 'i', 'ľ' => 'l', 'ĺ' => 'l', 'ň' => 'n', 'ó' => 'o', 'ö' => 'o', 'ô' => 'o',
            'ř' => 'r', 'š' => 's', 'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ü' => 'u', 'ý' => 'y',
            'ž' => 'z', 'œ' => 'oe', 'ß' => 'ss', 'æ' => 'ae'
        ];
    }
}
