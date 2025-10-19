<?php
declare(strict_types=1);

namespace Cms\Admin\Utils;

use Core\Database\Init as DB;

/**
 * Generuje unikátní slug v tabulce posts (per type).
 * Pokud slug koliduje, přidá -2, -3, ...
 */
final class Slugger
{
    public static function make(string $title): string
    {
        $s = mb_strtolower($title, 'UTF-8');
        $s = preg_replace('~[^\pL\d]+~u', '-', $s) ?? '';
        $s = trim($s, '-');
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        $s = preg_replace('~[^-\w]+~', '', $s) ?? '';
        return $s !== '' ? $s : 'item';
    }

    public static function uniqueInPosts(string $raw, string $type, ?int $excludeId = null): string
    {
        $base = self::make($raw);
        $slug = $base;
        $i = 2;

        while (self::existsInPosts($slug, $type, $excludeId)) {
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }

    private static function existsInPosts(string $slug, string $type, ?int $excludeId): bool
    {
        $q = DB::query()
            ->table('posts', 'p')
            ->select(['p.id'])
            ->where('p.slug', '=', $slug)
            ->where('p.type', '=', $type);

        if ($excludeId) {
            $q->where('p.id', '!=', $excludeId);
        }

        return (bool) $q->first();
    }
}
