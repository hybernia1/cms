<?php
declare(strict_types=1);

namespace Cms\Admin\Domain\PostTypes;

use InvalidArgumentException;

/**
 * @phpstan-type PostTypeConfig array{
 *     slug: string,
 *     nav: string,
 *     list: string,
 *     create: string,
 *     edit: string,
 *     label: string,
 *     icon: string,
 *     supports: array<int,string>
 * }
 */
final class PostTypeRegistry
{
    /**
     * @var array<string,PostTypeConfig>
     */
    private static array $types = [];

    /**
     * @var array<string,string>
     */
    private static array $typesBySlug = [];

    /**
     * @param array<string,mixed> $args
     */
    public static function register(string $type, array $args): void
    {
        $type = trim($type);
        if ($type === '') {
            throw new InvalidArgumentException('Post type slug must not be empty.');
        }

        $label = (string)($args['label'] ?? self::humanizeSlug($type));
        $slug = self::normalizeSlug($args['slug'] ?? null, $type);

        $defaults = [
            'slug'     => $slug,
            'nav'      => $label,
            'list'     => $label,
            'create'   => 'Create ' . $label,
            'edit'     => 'Edit ' . $label,
            'label'    => $label,
            'icon'     => 'bi-file-earmark',
            'supports' => [],
        ];

        /**
         * @var array{
         *     slug?:string,
         *     nav?:string,
         *     list?:string,
         *     create?:string,
         *     edit?:string,
         *     label?:string,
         *     icon?:string,
         *     supports?:mixed
         * } $config
         */
        $config = array_replace($defaults, $args);

        $config['slug'] = self::normalizeSlug($config['slug'] ?? null, $type);
        $supports = self::normalizeSupports($config['supports']);

        $previousSlug = self::$types[$type]['slug'] ?? null;
        if ($previousSlug !== null && $previousSlug !== $config['slug']) {
            unset(self::$typesBySlug[$previousSlug]);
        }

        $existingTypeForSlug = self::$typesBySlug[$config['slug']] ?? null;
        if ($existingTypeForSlug !== null && $existingTypeForSlug !== $type) {
            throw new InvalidArgumentException(sprintf(
                'Post type slug "%s" is already registered for type "%s".',
                $config['slug'],
                $existingTypeForSlug
            ));
        }

        self::$types[$type] = [
            'slug'     => $config['slug'],
            'nav'      => (string)$config['nav'],
            'list'     => (string)$config['list'],
            'create'   => (string)$config['create'],
            'edit'     => (string)$config['edit'],
            'label'    => (string)$config['label'],
            'icon'     => (string)$config['icon'],
            'supports' => $supports,
        ];

        self::$typesBySlug[$config['slug']] = $type;
    }

    /**
     * @return array<string,PostTypeConfig>
     */
    public static function all(): array
    {
        return self::$types;
    }

    /**
     * @return PostTypeConfig|null
     */
    public static function get(string $type): ?array
    {
        return self::$types[$type] ?? null;
    }

    public static function slugForType(string $type): ?string
    {
        return self::$types[$type]['slug'] ?? null;
    }

    public static function typeForSlug(string $slug): ?string
    {
        try {
            $normalized = self::normalizeSlug($slug, $slug);
        } catch (InvalidArgumentException $exception) {
            return null;
        }

        return self::$typesBySlug[$normalized] ?? null;
    }

    /**
     * @param mixed $supports
     * @return array<int,string>
     */
    private static function normalizeSupports(mixed $supports): array
    {
        if ($supports === null) {
            return [];
        }

        if (!is_array($supports)) {
            throw new InvalidArgumentException('Supports must be provided as an array of feature identifiers.');
        }

        $normalized = [];
        foreach ($supports as $feature) {
            $name = trim((string)$feature);
            if ($name === '') {
                continue;
            }

            self::assertFeatureSupported($name);
            $normalized[$name] = $name;
        }

        return array_values($normalized);
    }

    private static function normalizeSlug(mixed $slug, string $type): string
    {
        $value = trim((string)($slug ?? ''));
        if ($value === '') {
            $value = trim($type);
        }

        $value = strtolower($value);

        if ($value === '') {
            throw new InvalidArgumentException('Post type slug must not be empty.');
        }

        if (!preg_match('/^[a-z0-9_-]+$/', $value)) {
            throw new InvalidArgumentException('Post type slug may only contain lowercase letters, numbers, underscores, and hyphens.');
        }

        return $value;
    }

    private static function assertFeatureSupported(string $feature): void
    {
        if (in_array($feature, ['thumbnail', 'comments'], true)) {
            return;
        }

        if ($feature === 'terms') {
            return;
        }

        if (str_starts_with($feature, 'terms:')) {
            $taxonomy = substr($feature, strlen('terms:'));
            if ($taxonomy === '') {
                error_log('Terms feature must specify a taxonomy, e.g. "terms:category".');
                throw new InvalidArgumentException('Terms feature must specify a taxonomy, e.g. "terms:category".');
            }

            if (!preg_match('/^[a-z0-9_-]+$/', $taxonomy)) {
                error_log(sprintf('Invalid taxonomy identifier "%s".', $taxonomy));
                throw new InvalidArgumentException(sprintf('Invalid taxonomy identifier "%s".', $taxonomy));
            }

            return;
        }

        error_log(sprintf('Unsupported post type feature "%s".', $feature));
        throw new InvalidArgumentException(sprintf('Unsupported post type feature "%s".', $feature));
    }

    private static function humanizeSlug(string $slug): string
    {
        $replaced = str_replace(['-', '_'], ' ', $slug);
        return ucfirst($replaced);
    }
}
