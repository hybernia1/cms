<?php
declare(strict_types=1);

namespace Cms\Admin\Utils;

final class PermalinkSettings
{
    public const DEFAULT_POST_BASE = 'post';
    public const DEFAULT_PAGE_BASE = 'page';
    public const DEFAULT_TAG_BASE = 'tag';
    public const DEFAULT_CATEGORY_BASE = 'category';
    public const DEFAULT_AUTHOR_BASE = 'author';

    /**
     * @return array{seo_urls:bool,post_base:string,page_base:string,tag_base:string,category_base:string,author_base:string}
     */
    public static function defaults(): array
    {
        return [
            'seo_urls'      => true,
            'post_base'     => self::DEFAULT_POST_BASE,
            'page_base'     => self::DEFAULT_PAGE_BASE,
            'tag_base'      => self::DEFAULT_TAG_BASE,
            'category_base' => self::DEFAULT_CATEGORY_BASE,
            'author_base'   => self::DEFAULT_AUTHOR_BASE,
        ];
    }

    public static function sanitizeSlug(string $value, string $fallback): string
    {
        $value = trim($value);
        if ($value === '') {
            return $fallback;
        }

        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('~[^\pL\d]+~u', '-', $value) ?? '';
        $value = trim($value, '-');
        if ($value === '') {
            return $fallback;
        }

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($ascii) && $ascii !== '') {
            $value = strtolower($ascii);
        }

        $value = preg_replace('~[^a-z0-9-]+~', '', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $value : $fallback;
    }

    /**
     * @param array<string,mixed> $input
     * @return array{seo_urls:bool,post_base:string,page_base:string,tag_base:string,category_base:string,author_base:string}
     */
    public static function normalize(array $input): array
    {
        $defaults = self::defaults();

        return [
            'seo_urls'      => isset($input['seo_urls']) ? (bool)$input['seo_urls'] : $defaults['seo_urls'],
            'post_base'     => self::sanitizeSlug((string)($input['post_base'] ?? ''), $defaults['post_base']),
            'page_base'     => self::sanitizeSlug((string)($input['page_base'] ?? ''), $defaults['page_base']),
            'tag_base'      => self::sanitizeSlug((string)($input['tag_base'] ?? ''), $defaults['tag_base']),
            'category_base' => self::sanitizeSlug((string)($input['category_base'] ?? ''), $defaults['category_base']),
            'author_base'   => self::sanitizeSlug((string)($input['author_base'] ?? ''), $defaults['author_base']),
        ];
    }
}
