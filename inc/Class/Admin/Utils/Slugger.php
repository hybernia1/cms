<?php
declare(strict_types=1);

namespace Cms\Admin\Utils;

use Core\Database\Init as DB;
use Core\Text\Slug;

/**
 * Generuje unikátní slug v tabulce posts (per type).
 * Pokud slug koliduje, přidá -2, -3, ...
 */
final class Slugger
{
    public static function make(string $title): string
    {
        return Slug::from($title, 'item');
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

    public static function uniqueInTerms(string $raw, string $type, ?int $excludeId = null): string
    {
        $base = self::make($raw);
        $slug = $base;
        $i = 2;

        while (self::existsInTerms($slug, $type, $excludeId)) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    public static function uniqueInProducts(string $raw, ?int $excludeId = null): string
    {
        return self::uniqueInTable('products', $raw, $excludeId);
    }

    public static function uniqueInCategories(string $raw, ?int $excludeId = null): string
    {
        return self::uniqueInTable('categories', $raw, $excludeId);
    }

    private static function uniqueInTable(string $table, string $raw, ?int $excludeId = null): string
    {
        $base = self::make($raw);
        $slug = $base;
        $i = 2;

        while (self::existsInTable($table, $slug, $excludeId)) {
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

    private static function existsInTerms(string $slug, string $type, ?int $excludeId): bool
    {
        $q = DB::query()
            ->table('terms', 't')
            ->select(['t.id'])
            ->where('t.slug', '=', $slug)
            ->where('t.type', '=', $type);

        if ($excludeId) {
            $q->where('t.id', '!=', $excludeId);
        }

        return (bool) $q->first();
    }

    private static function existsInTable(string $table, string $slug, ?int $excludeId): bool
    {
        $q = DB::query()
            ->table($table, 't')
            ->select(['t.id'])
            ->where('t.slug', '=', $slug);

        if ($excludeId) {
            $q->where('t.id', '!=', $excludeId);
        }

        return (bool) $q->first();
    }
}
