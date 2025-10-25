<?php
declare(strict_types=1);

namespace Core\Widgets;

use InvalidArgumentException;
use Throwable;

/**
 * Runtime registry for sidebar/widgets.
 */
final class WidgetRegistry
{
    /**
     * @var array<string,array{
     *     id: string,
     *     name: string,
     *     description: string,
     *     areas: list<string>,
     *     render: callable,
     *     meta: array<string,mixed>
     * }>
     */
    private static array $widgets = [];

    /**
     * @param array<string,mixed> $args
     */
    public static function register(string $id, array $args): void
    {
        $id = self::normalizeId($id);
        $name = (string)($args['name'] ?? self::humanize($id));
        $description = (string)($args['description'] ?? '');
        $areas = self::normalizeAreas($args['areas'] ?? ['sidebar']);
        $render = $args['render'] ?? null;
        $meta = is_array($args['meta'] ?? null) ? $args['meta'] : [];

        $settings = WidgetSettingsStore::get($id);
        $active = WidgetSettingsStore::has($id) ? $settings['active'] : true;
        if ($settings['options'] !== []) {
            $meta = array_merge($meta, $settings['options']);
        }

        if (!is_callable($render)) {
            throw new InvalidArgumentException('Widget render callback must be callable.');
        }

        self::$widgets[$id] = [
            'id'          => $id,
            'name'        => $name,
            'description' => $description,
            'areas'       => $areas,
            'render'      => $render,
            'meta'        => $meta,
            'active'      => $active,
        ];
    }

    /**
     * @return list<array{
     *     id: string,
     *     name: string,
     *     description: string,
     *     areas: list<string>,
     *     render: callable,
     *     meta: array<string,mixed>,
     *     active: bool
     * }>
     */
    public static function all(): array
    {
        $widgets = array_values(self::$widgets);
        usort($widgets, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));
        return $widgets;
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     description: string,
     *     areas: list<string>,
     *     render: callable,
     *     meta: array<string,mixed>,
     *     active: bool
     * }|null
     */
    public static function get(string $id): ?array
    {
        $normalized = self::normalizeId($id);
        return self::$widgets[$normalized] ?? null;
    }

    /**
     * @return list<array{
     *     id: string,
     *     name: string,
     *     description: string,
     *     areas: list<string>,
     *     render: callable,
     *     meta: array<string,mixed>
     * }>
     */
    public static function forArea(string $area): array
    {
        $normalized = self::normalizeArea($area);
        $filtered = [];

        foreach (self::$widgets as $widget) {
            $active = !empty($widget['active']);
            if ($active && in_array($normalized, $widget['areas'], true)) {
                $filtered[] = $widget;
            }
        }

        usort($filtered, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $filtered;
    }

    public static function hasArea(string $area): bool
    {
        $normalized = self::normalizeArea($area);

        foreach (self::$widgets as $widget) {
            if (!empty($widget['active']) && in_array($normalized, $widget['areas'], true)) {
                return true;
            }
        }

        return false;
    }

    public static function renderArea(string $area, array $context = []): string
    {
        $widgets = self::forArea($area);
        if ($widgets === []) {
            return '';
        }

        $output = [];
        foreach ($widgets as $widget) {
            if (empty($widget['active'])) {
                continue;
            }

            try {
                $html = $widget['render']($context, $widget);
            } catch (Throwable $exception) {
                error_log('Widget "' . $widget['id'] . '" failed: ' . $exception->getMessage());
                continue;
            }

            if (!is_string($html)) {
                continue;
            }

            $trimmed = trim($html);
            if ($trimmed === '') {
                continue;
            }

            $output[] = self::wrapWidget($widget, $trimmed);
        }

        return implode("\n", $output);
    }

    /**
     * @param array<string,mixed> $options
     */
    public static function applySettings(string $id, bool $active, array $options = []): void
    {
        $normalized = self::normalizeId($id);
        if (!isset(self::$widgets[$normalized])) {
            return;
        }

        self::$widgets[$normalized]['meta'] = is_array(self::$widgets[$normalized]['meta'])
            ? self::$widgets[$normalized]['meta']
            : [];

        self::$widgets[$normalized]['active'] = $active;

        if ($options !== []) {
            self::$widgets[$normalized]['meta'] = array_merge(self::$widgets[$normalized]['meta'], $options);
        }
    }

    private static function wrapWidget(array $widget, string $body): string
    {
        $id = htmlspecialchars($widget['id'], ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars($widget['name'], ENT_QUOTES, 'UTF-8');

        return sprintf(
            '<section class="widget widget--%s"><h2 class="widget__title">%s</h2><div class="widget__body">%s</div></section>',
            $id,
            $name,
            $body
        );
    }

    private static function humanize(string $slug): string
    {
        $slug = str_replace(['-', '_'], ' ', $slug);
        $slug = preg_replace('~\s+~', ' ', $slug) ?: $slug;
        return ucwords($slug);
    }

    /**
     * @param mixed $areas
     * @return list<string>
     */
    private static function normalizeAreas(mixed $areas): array
    {
        if ($areas === null) {
            return ['sidebar'];
        }

        if (!is_array($areas)) {
            $areas = [$areas];
        }

        $normalized = [];
        foreach ($areas as $area) {
            $name = self::normalizeArea((string)$area);
            $normalized[$name] = $name;
        }

        if ($normalized === []) {
            $normalized['sidebar'] = 'sidebar';
        }

        return array_values($normalized);
    }

    private static function normalizeArea(string $area): string
    {
        $value = strtolower(trim($area));
        if ($value === '') {
            return 'sidebar';
        }

        $value = preg_replace('/[^a-z0-9\-_]/', '-', $value) ?? $value;
        return trim($value, '-');
    }

    private static function normalizeId(string $id): string
    {
        $trimmed = strtolower(trim($id));
        if ($trimmed === '') {
            throw new InvalidArgumentException('Widget identifier must not be empty.');
        }

        if (!preg_match('/^[a-z0-9\-_]+$/', $trimmed)) {
            throw new InvalidArgumentException('Widget identifier may only contain lowercase letters, numbers, hyphens and underscores.');
        }

        return $trimmed;
    }
}
