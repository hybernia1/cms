<?php
declare(strict_types=1);

namespace Core\Plugins;

use Cms\Admin\Utils\DateTimeFactory;
use Core\Database\Init as DB;
use Core\Database\SchemaChecker;

final class PluginSettingsStore
{
    /**
     * @var array<string,array{active:bool,options:array<string,mixed>}>|null
     */
    private static ?array $cache = null;

    private static ?bool $tableExists = null;

    /**
     * @return array<string,array{active:bool,options:array<string,mixed>}>
     */
    public static function all(): array
    {
        self::ensureLoaded();
        return self::$cache ?? [];
    }

    /**
     * @return array{active:bool,options:array<string,mixed>}
     */
    public static function get(string $slug): array
    {
        $normalized = self::normalizeSlug($slug);
        self::ensureLoaded();

        if (self::$cache !== null && isset(self::$cache[$normalized])) {
            return self::$cache[$normalized];
        }

        return ['active' => true, 'options' => []];
    }

    public static function set(string $slug, bool $active, ?array $options = null): void
    {
        $normalized = self::normalizeSlug($slug);
        if (!self::tableExists()) {
            return;
        }

        $current = self::get($normalized);
        $optionsToStore = $options === null
            ? $current['options']
            : self::normalizeOptions(array_replace($current['options'], $options));

        $payload = [
            'slug'       => $normalized,
            'active'     => $active ? 1 : 0,
            'options'    => $optionsToStore === [] ? null : json_encode($optionsToStore, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => DateTimeFactory::nowString(),
        ];

        $existingId = self::findId($normalized);
        if ($existingId !== null) {
            DB::query()->table('plugin_settings')->update($payload)->where('id', '=', $existingId)->execute();
        } else {
            $payload['created_at'] = DateTimeFactory::nowString();
            DB::query()->table('plugin_settings')->insert($payload)->execute();
        }

        self::$cache[$normalized] = [
            'active'  => $active,
            'options' => $optionsToStore,
        ];

        PluginRegistry::applySettings($normalized, $active, $optionsToStore);
    }

    public static function refresh(): void
    {
        self::$cache = null;
    }

    public static function has(string $slug): bool
    {
        $normalized = self::normalizeSlug($slug);
        self::ensureLoaded();

        return self::$cache !== null && array_key_exists($normalized, self::$cache);
    }

    private static function ensureLoaded(): void
    {
        if (self::$cache !== null) {
            return;
        }

        if (!self::tableExists()) {
            self::$cache = [];
            return;
        }

        $rows = DB::query()
            ->table('plugin_settings')
            ->select(['slug', 'active', 'options'])
            ->get() ?? [];

        $cache = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['slug'])) {
                continue;
            }

            $slug = self::normalizeSlug((string)$row['slug']);
            $active = (int)($row['active'] ?? 1) === 1;
            $options = self::decodeOptions($row['options'] ?? null);
            $cache[$slug] = ['active' => $active, 'options' => $options];
        }

        self::$cache = $cache;
    }

    private static function decodeOptions(mixed $options): array
    {
        if (is_string($options) && $options !== '') {
            $decoded = json_decode($options, true);
            if (is_array($decoded)) {
                return self::normalizeOptions($decoded);
            }
        }

        if (is_array($options)) {
            return self::normalizeOptions($options);
        }

        return [];
    }

    /**
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    private static function normalizeOptions(array $options): array
    {
        $normalized = [];
        foreach ($options as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    private static function findId(string $slug): ?int
    {
        $row = DB::query()
            ->table('plugin_settings')
            ->select(['id'])
            ->where('slug', '=', $slug)
            ->first();

        if (!is_array($row) || !isset($row['id'])) {
            return null;
        }

        $id = (int)$row['id'];
        return $id > 0 ? $id : null;
    }

    private static function tableExists(): bool
    {
        if (self::$tableExists !== null) {
            return self::$tableExists;
        }

        $checker = new SchemaChecker();
        self::$tableExists = $checker->hasTable('plugin_settings');

        return self::$tableExists;
    }

    private static function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        return preg_replace('/[^a-z0-9\-_.]/', '', $slug) ?? '';
    }
}
