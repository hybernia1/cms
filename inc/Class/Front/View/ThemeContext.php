<?php
declare(strict_types=1);

namespace Cms\Front\View;

use Cms\Admin\Utils\LinkGenerator;
use RuntimeException;

final class ThemeContext
{
    private static ?self $instance = null;

    private ThemeSiteData $site;
    private ThemeMetaData $meta;
    private ThemeMetaData $defaultMeta;
    private ThemeThemeData $theme;
    private ThemeNavigationData $navigation;
    private ThemeCommentsData $comments;
    private LinkGenerator $links;
    /** @var array<string,callable|null> */
    private array $formatters;

    /**
     * @param array<string,mixed> $site
     * @param array<string,mixed> $meta
     * @param array<string,mixed> $theme
     * @param array<string,mixed> $navigation
     * @param array<int,array<string,mixed>> $comments
     * @param array<string,callable|null> $formatters
     */
    public function __construct(
        array $site,
        array $meta,
        array $theme,
        array $navigation,
        array $comments,
        LinkGenerator $links,
        array $formatters = []
    ) {
        $this->site = ThemeSiteData::fromArray($site);
        $defaultMeta = ThemeMetaData::fromArray($meta);
        $this->meta = $defaultMeta;
        $this->defaultMeta = clone $defaultMeta;
        $this->theme = ThemeThemeData::fromArray($theme);
        $this->navigation = ThemeNavigationData::fromArray($navigation);
        $this->comments = ThemeCommentsData::fromArray($comments);
        $this->links = $links;
        $this->formatters = $formatters;
    }

    public function __clone()
    {
        $this->site = clone $this->site;
        $this->meta = clone $this->meta;
        $this->defaultMeta = clone $this->defaultMeta;
        $this->theme = clone $this->theme;
        $this->navigation = clone $this->navigation;
        $this->comments = clone $this->comments;
    }

    public static function setInstance(self $context): void
    {
        self::$instance = $context;
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new RuntimeException('Theme context has not been initialised.');
        }

        return self::$instance;
    }

    public function duplicate(): self
    {
        return clone $this;
    }

    /**
     * @param array<string,mixed>|ThemeSiteData $site
     */
    public function applySite(array|ThemeSiteData $site): void
    {
        if ($site instanceof ThemeSiteData) {
            $this->site = clone $site;
            return;
        }

        $this->site = ThemeSiteData::override($this->site, $site);
    }

    /**
     * @param array<string,mixed>|ThemeMetaData|null $meta
     */
    public function applyMeta(array|ThemeMetaData|null $meta): void
    {
        if ($meta instanceof ThemeMetaData) {
            $this->meta = clone $meta;
            return;
        }

        if ($meta === null) {
            $this->meta = clone $this->defaultMeta;
            return;
        }

        $this->meta = ThemeMetaData::merge($this->defaultMeta, $meta);
    }

    /**
     * @param array<string,mixed>|ThemeThemeData $theme
     */
    public function applyTheme(array|ThemeThemeData $theme): void
    {
        if ($theme instanceof ThemeThemeData) {
            $this->theme = clone $theme;
            return;
        }

        $this->theme = ThemeThemeData::fromArray($theme);
    }

    /**
     * @param array<string,mixed>|ThemeNavigationData $navigation
     */
    public function applyNavigation(array|ThemeNavigationData $navigation): void
    {
        if ($navigation instanceof ThemeNavigationData) {
            $this->navigation = clone $navigation;
            return;
        }

        $this->navigation = ThemeNavigationData::fromArray($navigation);
    }

    /**
     * @param array<int,array<string,mixed>>|ThemeCommentsData|null $comments
     */
    public function applyComments(array|ThemeCommentsData|null $comments = null, ?int $count = null, ?bool $allowed = null): void
    {
        if ($comments instanceof ThemeCommentsData) {
            $this->comments = $comments->with(null, $count, $allowed);
            return;
        }

        $this->comments = $this->comments->with($comments, $count, $allowed);
    }

    /**
     * @param array<string,callable|null> $formatters
     */
    public function applyFormatters(array $formatters): void
    {
        $this->formatters = $formatters;
    }

    public function applyLinks(LinkGenerator $links): void
    {
        $this->links = $links;
    }

    public function site(): ThemeSiteData
    {
        return $this->site;
    }

    public function meta(): ThemeMetaData
    {
        return $this->meta;
    }

    public function theme(): ThemeThemeData
    {
        return $this->theme;
    }

    public function navigation(): ThemeNavigationData
    {
        return $this->navigation;
    }

    public function comments(): ThemeCommentsData
    {
        return $this->comments;
    }

    /**
     * @return array<string,callable|null>
     */
    public function formatters(): array
    {
        return $this->formatters;
    }

    public function links(): LinkGenerator
    {
        return $this->links;
    }

    /**
     * @return array<string,mixed>
     */
    public function toViewData(): array
    {
        return [
            'site' => $this->site,
            'meta' => $this->meta,
            'theme' => $this->theme,
            'navigation' => $this->navigation,
            'comments' => $this->comments,
            'links' => $this->links,
            'format' => $this->formatters,
        ];
    }
}

abstract class ThemeData
{
    /** @param array<string,mixed> $data */
    public function __construct(protected array $data)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    protected static function cleanString(mixed $value): string
    {
        if (is_string($value)) {
            $value = trim($value);
        } elseif ($value === null) {
            $value = '';
        } else {
            $value = trim((string)$value);
        }

        return $value;
    }

    protected static function cleanNonEmptyString(mixed $value, string $default = ''): string
    {
        $clean = self::cleanString($value);
        return $clean !== '' ? $clean : $default;
    }

    protected static function cleanNullableString(mixed $value): ?string
    {
        $clean = self::cleanString($value);
        return $clean === '' ? null : $clean;
    }

    protected static function cleanBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ($normalized === 'true' || $normalized === '1') {
                return true;
            }
            if ($normalized === 'false' || $normalized === '0' || $normalized === '') {
                return false;
            }
        }

        return (bool)$value;
    }

    /**
     * @param mixed $value
     * @return array<int|string,mixed>
     */
    protected static function cleanArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}

final class ThemeSiteData extends ThemeData
{
    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $title = self::cleanNonEmptyString($data['title'] ?? null, 'Web');
        $description = self::cleanString($data['description'] ?? null);
        $url = self::cleanString($data['url'] ?? null);
        $email = self::cleanString($data['email'] ?? null);
        $locale = self::cleanNonEmptyString($data['locale'] ?? null, 'cs');
        $timezone = self::cleanString($data['timezone'] ?? null);
        $dateFormat = self::cleanString($data['date_format'] ?? null);
        $timeFormat = self::cleanString($data['time_format'] ?? null);
        $dateTimeFormat = self::cleanString($data['datetime_format'] ?? null);
        $favicon = self::cleanString($data['favicon'] ?? null);

        return new self([
            'title' => $title,
            'description' => $description,
            'url' => $url,
            'email' => $email,
            'locale' => $locale,
            'timezone' => $timezone,
            'date_format' => $dateFormat,
            'time_format' => $timeFormat,
            'datetime_format' => $dateTimeFormat,
            'favicon' => $favicon,
        ]);
    }

    /**
     * @param array<string,mixed> $overrides
     */
    public static function override(self $current, array $overrides): self
    {
        return self::fromArray(array_merge($current->toArray(), $overrides));
    }

    public function value(string $key, mixed $default = null): mixed
    {
        return match ($key) {
            'title' => $this->title(),
            'description' => $this->description(),
            'url' => $this->url(),
            'email' => $this->email(),
            'locale' => $this->locale(),
            'timezone' => $this->timezone(),
            'date_format' => $this->dateFormat(),
            'time_format' => $this->timeFormat(),
            'datetime_format' => $this->dateTimeFormat(),
            'favicon' => $this->favicon(),
            default => $this->data[$key] ?? $default,
        };
    }

    public function title(): string
    {
        return $this->data['title'];
    }

    public function description(): string
    {
        return $this->data['description'];
    }

    public function url(): string
    {
        return $this->data['url'];
    }

    public function email(): string
    {
        return $this->data['email'];
    }

    public function locale(): string
    {
        return $this->data['locale'];
    }

    public function timezone(): string
    {
        return $this->data['timezone'];
    }

    public function dateFormat(): string
    {
        return $this->data['date_format'];
    }

    public function timeFormat(): string
    {
        return $this->data['time_format'];
    }

    public function dateTimeFormat(): string
    {
        return $this->data['datetime_format'];
    }

    public function favicon(): string
    {
        return $this->data['favicon'];
    }
}

final class ThemeMetaData extends ThemeData
{
    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $title = self::cleanNonEmptyString($data['title'] ?? null, 'Web');
        $description = self::cleanNullableString($data['description'] ?? null);
        $canonical = self::cleanNullableString($data['canonical'] ?? null);
        $extra = self::sanitizeExtra(self::cleanArray($data['extra'] ?? null));
        $bodyClass = self::cleanString($data['body_class'] ?? null);

        return new self([
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'extra' => $extra,
            'body_class' => $bodyClass,
        ]);
    }

    /**
     * @param array<string,mixed> $incoming
     */
    public static function merge(self $defaults, array $incoming): self
    {
        $base = $defaults->toArray();

        if (array_key_exists('title', $incoming)) {
            $base['title'] = self::cleanNonEmptyString($incoming['title'], $base['title']);
        }
        if (array_key_exists('description', $incoming)) {
            $base['description'] = self::cleanNullableString($incoming['description']);
        }
        if (array_key_exists('canonical', $incoming)) {
            $base['canonical'] = self::cleanNullableString($incoming['canonical']);
        }
        if (array_key_exists('body_class', $incoming)) {
            $base['body_class'] = self::cleanString($incoming['body_class']);
        }
        if (isset($incoming['extra']) && is_array($incoming['extra'])) {
            $baseExtra = is_array($base['extra']) ? $base['extra'] : [];
            $incomingExtra = self::sanitizeExtra($incoming['extra']);
            $base['extra'] = $baseExtra === [] && $incomingExtra === []
                ? []
                : array_merge($baseExtra, $incomingExtra);
        }

        return new self($base);
    }

    /**
     * @param array<int|string,mixed> $extra
     * @return array<int|string,mixed>
     */
    private static function sanitizeExtra(array $extra): array
    {
        $sanitized = [];
        foreach ($extra as $key => $value) {
            if (is_array($value)) {
                $entry = [];
                foreach ($value as $innerKey => $innerValue) {
                    if (is_string($innerKey)) {
                        $entry[$innerKey] = self::cleanString($innerValue);
                    }
                }
                $sanitized[$key] = $entry;
                continue;
            }
            $sanitized[$key] = self::cleanString($value);
        }
        return $sanitized;
    }

    public function value(string $key, mixed $default = null): mixed
    {
        return match ($key) {
            'title' => $this->title(),
            'description' => $this->description(),
            'canonical' => $this->canonical(),
            'extra' => $this->extra(),
            'body_class' => $this->bodyClass(),
            default => $this->data[$key] ?? $default,
        };
    }

    public function title(): string
    {
        return $this->data['title'];
    }

    public function description(): ?string
    {
        return $this->data['description'];
    }

    public function canonical(): ?string
    {
        return $this->data['canonical'];
    }

    /**
     * @return array<int|string,mixed>
     */
    public function extra(): array
    {
        return $this->data['extra'];
    }

    public function bodyClass(): string
    {
        return $this->data['body_class'];
    }
}

final class ThemeThemeData extends ThemeData
{
    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $paletteRaw = self::cleanArray($data['palette'] ?? null);
        $palette = [];
        foreach ($paletteRaw as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $clean = self::cleanString($value);
            if ($clean === '') {
                continue;
            }
            $palette[$key] = $clean;
        }

        $supports = [];
        $rawSupports = $data['supports'] ?? [];
        if (is_array($rawSupports)) {
            foreach ($rawSupports as $item) {
                $supports[] = self::cleanString($item);
            }
        }

        $asset = $data['asset'] ?? null;
        if (!is_callable($asset)) {
            $asset = null;
        }

        return new self([
            'slug' => self::cleanNonEmptyString($data['slug'] ?? null, 'classic'),
            'name' => self::cleanNonEmptyString($data['name'] ?? null, 'Classic'),
            'version' => self::cleanString($data['version'] ?? null),
            'author' => self::cleanString($data['author'] ?? null),
            'description' => self::cleanString($data['description'] ?? null),
            'supports' => $supports,
            'palette' => $palette,
            'asset' => $asset,
            'missing_template' => self::cleanBool($data['missing_template'] ?? false),
            'missing_template_error' => self::cleanNullableString($data['missing_template_error'] ?? null),
        ]);
    }

    public function value(string $key, mixed $default = null): mixed
    {
        return match ($key) {
            'slug' => $this->slug(),
            'name' => $this->name(),
            'version' => $this->version(),
            'author' => $this->author(),
            'description' => $this->description(),
            'supports' => $this->supports(),
            'palette' => $this->palette(),
            'asset' => $this->asset(),
            'missing_template' => $this->missingTemplate(),
            'missing_template_error' => $this->missingTemplateError(),
            default => $this->data[$key] ?? $default,
        };
    }

    public function slug(): string
    {
        return $this->data['slug'];
    }

    public function name(): string
    {
        return $this->data['name'];
    }

    public function version(): string
    {
        return $this->data['version'];
    }

    public function author(): string
    {
        return $this->data['author'];
    }

    public function description(): string
    {
        return $this->data['description'];
    }

    /**
     * @return list<string>
     */
    public function supports(): array
    {
        return $this->data['supports'];
    }

    /**
     * @return array<string,string>
     */
    public function palette(): array
    {
        return $this->data['palette'];
    }

    public function paletteValue(string $key, ?string $default = null): ?string
    {
        return $this->data['palette'][$key] ?? $default;
    }

    /**
     * @return callable|null
     */
    public function asset(): ?callable
    {
        return $this->data['asset'];
    }

    public function missingTemplate(): bool
    {
        return $this->data['missing_template'];
    }

    public function missingTemplateError(): ?string
    {
        return $this->data['missing_template_error'];
    }
}

final class ThemeNavigationData extends ThemeData
{
    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $menus = [];
        foreach ($data as $location => $menu) {
            if (!is_string($location)) {
                continue;
            }
            $menus[$location] = ThemeMenuData::fromArray(is_array($menu) ? $menu : []);
        }

        return new self(['menus' => $menus]);
    }

    public function value(string $key, mixed $default = null): mixed
    {
        if ($key === 'menus') {
            return $this->menus();
        }

        $menu = $this->menu($key);
        if ($menu !== null) {
            return $menu;
        }

        return $default;
    }

    /**
     * @return array<string,ThemeMenuData>
     */
    public function menus(): array
    {
        return $this->data['menus'];
    }

    public function menu(string $location): ?ThemeMenuData
    {
        return $this->data['menus'][$location] ?? null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function items(string $location): array
    {
        $menu = $this->menu($location);
        return $menu !== null ? $menu->items() : [];
    }
}

final class ThemeMenuData extends ThemeData
{
    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $items = self::sanitizeItems(self::cleanArray($data['items'] ?? null));

        return new self([
            'id' => isset($data['id']) ? (int)$data['id'] : 0,
            'name' => self::cleanString($data['name'] ?? null),
            'slug' => self::cleanString($data['slug'] ?? null),
            'location' => self::cleanString($data['location'] ?? null),
            'description' => self::cleanString($data['description'] ?? null),
            'items' => $items,
        ]);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function items(): array
    {
        return $this->data['items'];
    }

    /**
     * @param array<int,mixed> $items
     * @return array<int,array<string,mixed>>
     */
    private static function sanitizeItems(array $items): array
    {
        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $children = [];
            if (isset($item['children']) && is_array($item['children'])) {
                $children = self::sanitizeItems($item['children']);
            }

            $normalized[] = [
                'id' => isset($item['id']) ? (int)$item['id'] : 0,
                'menu_id' => isset($item['menu_id']) ? (int)$item['menu_id'] : 0,
                'parent_id' => isset($item['parent_id']) && (int)$item['parent_id'] > 0 ? (int)$item['parent_id'] : null,
                'title' => self::cleanString($item['title'] ?? null),
                'url' => self::cleanString($item['url'] ?? null),
                'target' => self::cleanNonEmptyString($item['target'] ?? null, '_self'),
                'css_class' => self::cleanString($item['css_class'] ?? null),
                'sort_order' => isset($item['sort_order']) ? (int)$item['sort_order'] : 0,
                'link_type' => self::cleanString($item['link_type'] ?? null),
                'link_reference' => self::cleanString($item['link_reference'] ?? null),
                'link_meta' => self::cleanArray($item['link_meta'] ?? null),
                'link_valid' => self::cleanBool($item['link_valid'] ?? false),
                'link_reason' => self::cleanString($item['link_reason'] ?? null),
                'children' => $children,
            ];
        }

        return $normalized;
    }
}

final class ThemeCommentsData extends ThemeData
{
    public function __construct(array $data)
    {
        $items = self::sanitizeItems($data['items'] ?? []);
        $count = isset($data['count']) ? (int)$data['count'] : self::countItems($items);
        $allowed = isset($data['allowed']) ? self::cleanBool($data['allowed']) : false;

        parent::__construct([
            'items' => $items,
            'count' => $count < 0 ? 0 : $count,
            'allowed' => $allowed,
        ]);
    }

    /**
     * @param array<int,array<string,mixed>> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(['items' => $data]);
    }

    public function with(?array $items = null, ?int $count = null, ?bool $allowed = null): self
    {
        $base = $this->toArray();
        if ($items !== null) {
            $base['items'] = $items;
        }
        if ($count !== null) {
            $base['count'] = $count;
        }
        if ($allowed !== null) {
            $base['allowed'] = $allowed;
        }

        return new self($base);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function items(): array
    {
        return $this->data['items'];
    }

    public function count(): int
    {
        return $this->data['count'];
    }

    public function allowed(): bool
    {
        return $this->data['allowed'];
    }

    public function value(string $key, mixed $default = null): mixed
    {
        return match ($key) {
            'items' => $this->items(),
            'count' => $this->count(),
            'allowed' => $this->allowed(),
            default => $default,
        };
    }

    /**
     * @param array<int,mixed> $items
     * @return array<int,array<string,mixed>>
     */
    private static function sanitizeItems(array $items): array
    {
        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $children = [];
            if (isset($item['children']) && is_array($item['children'])) {
                $children = self::sanitizeItems($item['children']);
            }

            $id = isset($item['id']) ? (int)$item['id'] : 0;
            $normalized[] = [
                'id' => $id,
                'parent_id' => isset($item['parent_id']) && (int)$item['parent_id'] > 0 ? (int)$item['parent_id'] : null,
                'thread_root_id' => isset($item['thread_root_id']) && (int)$item['thread_root_id'] > 0 ? (int)$item['thread_root_id'] : ($id > 0 ? $id : null),
                'author' => ThemeData::cleanNonEmptyString($item['author'] ?? null, 'Anonym'),
                'content' => is_string($item['content'] ?? null) ? $item['content'] : (string)($item['content'] ?? ''),
                'created_at' => ThemeData::cleanString($item['created_at'] ?? null),
                'created_at_iso' => ThemeData::cleanString($item['created_at_iso'] ?? null),
                'created_at_raw' => ThemeData::cleanString($item['created_at_raw'] ?? null),
                'children' => $children,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int,array<string,mixed>> $items
     */
    private static function countItems(array $items): int
    {
        $count = 0;
        foreach ($items as $item) {
            $count++;
            if (isset($item['children']) && is_array($item['children'])) {
                $count += self::countItems($item['children']);
            }
        }
        return $count;
    }
}
