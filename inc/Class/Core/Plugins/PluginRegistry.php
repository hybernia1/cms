<?php
declare(strict_types=1);

namespace Core\Plugins;

use InvalidArgumentException;

/**
 * Registry for runtime plugin metadata.
 */
final class PluginRegistry
{
    /**
     * @var array<string,array{
     *     slug: string,
     *     name: string,
     *     description: string,
     *     version: string,
     *     author: string,
     *     homepage: string|null,
     *     admin_url: string|null,
     *     active: bool,
     *     meta: array<string,mixed>
     * }>
     */
    private static array $plugins = [];

    /**
     * @param array<string,mixed> $args
     */
    public static function register(string $slug, array $args): void
    {
        $slug = self::normalizeSlug($slug);
        $name = (string)($args['name'] ?? self::humanize($slug));
        $description = (string)($args['description'] ?? '');
        $version = (string)($args['version'] ?? '');
        $author = (string)($args['author'] ?? '');
        $homepage = isset($args['homepage']) && $args['homepage'] !== ''
            ? (string)$args['homepage']
            : null;
        $adminUrl = isset($args['admin_url']) && $args['admin_url'] !== ''
            ? (string)$args['admin_url']
            : null;
        $baseActive = array_key_exists('active', $args) ? (bool)$args['active'] : true;
        $meta = is_array($args['meta'] ?? null) ? $args['meta'] : [];

        $settings = PluginSettingsStore::get($slug);
        $active = PluginSettingsStore::has($slug) ? $settings['active'] : $baseActive;
        if ($settings['options'] !== []) {
            $meta = array_merge($meta, $settings['options']);
        }

        self::$plugins[$slug] = [
            'slug'        => $slug,
            'name'        => $name,
            'description' => $description,
            'version'     => $version,
            'author'      => $author,
            'homepage'    => $homepage,
            'admin_url'   => $adminUrl,
            'active'      => $active,
            'meta'        => $meta,
        ];
    }

    /**
     * @return list<array{
     *     slug: string,
     *     name: string,
     *     description: string,
     *     version: string,
     *     author: string,
     *     homepage: string|null,
     *     admin_url: string|null,
     *     active: bool,
     *     meta: array<string,mixed>
     * }>
     */
    public static function all(): array
    {
        $plugins = array_values(self::$plugins);
        usort($plugins, static function (array $a, array $b): int {
            $nameComparison = strcasecmp($a['name'], $b['name']);
            if ($nameComparison !== 0) {
                return $nameComparison;
            }

            return strcmp($a['slug'], $b['slug']);
        });

        return $plugins;
    }

    /**
     * @return array{
     *     slug: string,
     *     name: string,
     *     description: string,
     *     version: string,
     *     author: string,
     *     homepage: string|null,
     *     admin_url: string|null,
     *     active: bool,
     *     meta: array<string,mixed>
     * }|null
     */
    public static function get(string $slug): ?array
    {
        $normalized = self::normalizeSlug($slug);
        return self::$plugins[$normalized] ?? null;
    }

    public static function isActive(string $slug): bool
    {
        $plugin = self::get($slug);
        return $plugin ? $plugin['active'] : false;
    }

    /**
     * Apply updated settings to runtime registry without reloading plugin files.
     *
     * @param array<string,mixed> $options
     */
    public static function applySettings(string $slug, bool $active, array $options = []): void
    {
        $normalized = self::normalizeSlug($slug);
        if (!isset(self::$plugins[$normalized])) {
            return;
        }

        self::$plugins[$normalized]['active'] = $active;

        if (!isset(self::$plugins[$normalized]['meta']) || !is_array(self::$plugins[$normalized]['meta'])) {
            self::$plugins[$normalized]['meta'] = [];
        }

        if ($options !== []) {
            self::$plugins[$normalized]['meta'] = array_merge(self::$plugins[$normalized]['meta'], $options);
        }
    }

    private static function humanize(string $slug): string
    {
        $slug = str_replace(['-', '_'], ' ', $slug);
        $slug = preg_replace('~\s+~', ' ', $slug) ?: $slug;
        return ucwords($slug);
    }

    private static function normalizeSlug(string $slug): string
    {
        $trimmed = strtolower(trim($slug));
        if ($trimmed === '') {
            throw new InvalidArgumentException('Plugin slug must not be empty.');
        }

        if (!preg_match('/^[a-z0-9\-_.]+$/', $trimmed)) {
            throw new InvalidArgumentException('Plugin slug may only contain letters, numbers, dots, underscores and hyphens.');
        }

        return $trimmed;
    }
}
