<?php
declare(strict_types=1);

namespace Cms\Front\View;

use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Utils\DateTimeFactory;
use Cms\Admin\Utils\LinkGenerator;
use Cms\Admin\View\ViewEngine;
use Throwable;

final class ThemeViewEngine
{
    private ViewEngine $engine;
    private CmsSettings $settings;
    private LinkGenerator $links;
    private string $themeSlug;
    private string $baseDir;
    /** @var array<string,mixed> */
    private array $themeInfo = [];
    /** @var array<string,mixed> */
    private array $defaultMeta = [];
    /** @var array<string,callable|null> */
    private array $formatters = [];
    private string $dateFormat;
    private string $timeFormat;
    private string $dateTimeFormat;
    private bool $missingTemplate = false;
    private ?string $missingTemplateError = null;

    public function __construct(?CmsSettings $settings = null, ?LinkGenerator $links = null)
    {
        $this->settings = $settings ?? new CmsSettings();
        $this->links = $links ?? new LinkGenerator();
        $this->baseDir = defined('BASE_DIR') ? BASE_DIR : __DIR__ . '/../../../..';

        $this->dateFormat = $this->settings->dateFormat() ?: 'Y-m-d';
        $this->timeFormat = $this->settings->timeFormat() ?: 'H:i';
        $this->dateTimeFormat = trim($this->dateFormat . ' ' . $this->timeFormat);
        if ($this->dateTimeFormat === '') {
            $this->dateTimeFormat = 'Y-m-d H:i';
        }

        $this->engine = new ViewEngine($this->baseDir . '/themes');
        $this->setTheme($this->settings->themeSlug());
        $this->shareDefaults();
    }

    public function setTheme(string $slug): void
    {
        $trimmed = trim($slug);
        if ($trimmed === '') {
            throw MissingThemeException::forEmptySlug($this->baseDir . '/themes');
        }

        $this->themeSlug = $trimmed;
        $this->missingTemplate = false;
        $this->missingTemplateError = null;

        $themePath = $this->baseDir . '/themes/' . $trimmed . '/templates';
        if (!is_dir($themePath)) {
            throw MissingThemeException::forSlug($trimmed, $themePath);
        }

        $this->engine->setBasePaths([$themePath]);
        $this->loadThemeManifest($trimmed);
        $this->shareThemeContext();
        $this->loadThemeFunctions($trimmed);
    }

    public function share(array $data): void
    {
        $this->engine->share($data);
    }

    public function render(string $template, array $data = []): void
    {
        $payload = $this->prepareData($data);
        try {
            $this->engine->render($template, $payload);
        } catch (Throwable $exception) {
            $this->markMissingTemplate($exception);
            throw $exception;
        }
    }

    public function renderWithLayout(?string $layout, string $template, array $data = []): void
    {
        $payload = $this->prepareData($data);

        try {
            if ($layout === null) {
                $this->engine->render($template, $payload);
                return;
            }

            $this->engine->render($layout, $payload, function () use ($template, $payload): void {
                $this->engine->render($template, $payload);
            });
        } catch (Throwable $exception) {
            $this->markMissingTemplate($exception);
            throw $exception;
        }
    }

    public function themeSlug(): string
    {
        return $this->themeSlug;
    }

    public function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $base = $this->basePath();
        $url = $base . '/themes/' . $this->themeSlug . '/' . $path;
        $version = isset($this->themeInfo['version']) ? (string)$this->themeInfo['version'] : '';
        if ($version !== '') {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . 'ver=' . rawurlencode($version);
        }
        return $url;
    }

    private function basePath(): string
    {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = str_replace('\\', '/', (string)dirname($script));
        $dir = rtrim($dir, '/');
        if ($dir === '' || $dir === '.') {
            return '';
        }
        return $dir;
    }

    private function shareDefaults(): void
    {
        $siteTitle = $this->settings->siteTitle();
        $siteUrl = $this->settings->siteUrl();
        $siteTagline = $this->settings->siteTagline();
        $siteLocale = $this->settings->siteLocale();
        $siteEmail = $this->settings->siteEmail();
        $siteTimezone = $this->settings->timezone();
        $siteFavicon = $this->settings->siteFavicon();
        $siteLogo = $this->settings->siteLogo();
        $siteSocialImage = $this->settings->siteSocialImage();

        $this->defaultMeta = $this->buildDefaultMeta($siteTitle, $siteTagline, $siteUrl, $siteLocale);
        $this->createFormatters();

        $this->share([
            'site' => [
                'title' => $siteTitle,
                'url' => $siteUrl,
                'description' => $siteTagline,
                'email' => $siteEmail,
                'locale' => $siteLocale,
                'timezone' => $siteTimezone,
                'date_format' => $this->dateFormat,
                'time_format' => $this->timeFormat,
                'datetime_format' => $this->dateTimeFormat,
                'favicon' => $siteFavicon,
                'logo' => $siteLogo,
                'social_image' => $siteSocialImage,
            ],
            'links' => $this->links,
            'navigation' => [],
            'meta' => $this->defaultMeta,
            'format' => $this->formatters,
        ]);

        $this->shareThemeContext();
    }

    private function shareThemeContext(): void
    {
        $this->share([
            'theme' => $this->buildThemeContext(),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function buildThemeContext(): array
    {
        $info = $this->themeInfo;
        $info['slug'] = $this->themeSlug;
        $info['asset'] = fn (string $file): string => $this->asset($file);
        $info['missing_template'] = $this->missingTemplate;
        $info['missing_template_error'] = $this->missingTemplateError;
        return $info;
    }

    private function markMissingTemplate(Throwable $exception): void
    {
        $this->missingTemplate = true;
        $this->missingTemplateError = $exception->getMessage();
        $this->shareThemeContext();
    }

    private function loadThemeManifest(string $slug): void
    {
        $defaults = [
            'slug' => $slug,
            'name' => ucfirst($slug),
            'version' => '',
            'author' => '',
            'description' => '',
            'supports' => [],
            'palette' => [],
        ];

        $file = $this->baseDir . '/themes/' . $slug . '/theme.json';
        $info = $defaults;
        if (is_file($file)) {
            $raw = file_get_contents($file);
            if ($raw !== false) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $info = array_merge($info, $decoded);
                } else {
                    error_log(sprintf("Theme manifest for '%s' is invalid or unreadable: %s", $slug, $file));
                }
            }
        }

        if (!is_array($info['supports'] ?? null)) {
            $info['supports'] = [];
        }
        if (!is_array($info['palette'] ?? null)) {
            $info['palette'] = [];
        }
        unset($info['asset']);

        $this->themeInfo = $info;
    }

    private function createFormatters(): void
    {
        $this->formatters = [
            'date' => fn (?string $value): ?string => $this->formatValue($value, $this->dateFormat),
            'time' => fn (?string $value): ?string => $this->formatValue($value, $this->timeFormat),
            'datetime' => fn (?string $value): ?string => $this->formatValue($value, $this->dateTimeFormat),
            'iso' => fn (?string $value): ?string => $this->formatIso($value),
        ];
    }

    private function formatValue(?string $value, string $format): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }
        $useFormat = $format !== '' ? $format : 'Y-m-d H:i';
        $dateTime = DateTimeFactory::fromStorage($trimmed);
        if ($dateTime === null) {
            return null;
        }
        return $dateTime->format($useFormat);
    }

    private function formatIso(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }
        $dateTime = DateTimeFactory::fromStorage($trimmed);
        if ($dateTime === null) {
            return null;
        }
        return $dateTime->format(DATE_ATOM);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function prepareData(array $data): array
    {
        if (isset($data['meta']) && is_array($data['meta'])) {
            $incoming = $data['meta'];
            $merged = array_merge($this->defaultMeta, $incoming);

            $baseExtra = is_array($this->defaultMeta['extra'] ?? null) ? $this->defaultMeta['extra'] : [];
            $incomingExtra = is_array($incoming['extra'] ?? null) ? $incoming['extra'] : [];
            $merged['extra'] = $this->mergeMetaExtra($baseExtra, $incomingExtra);

            $baseStructured = is_array($this->defaultMeta['structured_data'] ?? null) ? $this->defaultMeta['structured_data'] : [];
            $incomingStructured = is_array($incoming['structured_data'] ?? null) ? $incoming['structured_data'] : [];
            $merged['structured_data'] = $this->mergeStructuredData($baseStructured, $incomingStructured);

            $data['meta'] = $this->normalizeMeta($merged);
        } else {
            $data['meta'] = $this->normalizeMeta($this->defaultMeta);
        }

        return $data;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildDefaultMeta(string $title, string $description, string $siteUrl, string $locale): array
    {
        $description = trim($description);
        $canonical = $this->absoluteUrl($siteUrl);
        $localeNormalized = $locale !== '' ? str_replace('_', '-', $locale) : 'cs';

        $extra = [
            'og:site_name' => $title,
            'og:type' => 'website',
            'og:locale' => $localeNormalized,
            'twitter:card' => 'summary_large_image',
        ];

        if ($description !== '') {
            $extra['og:description'] = $description;
            $extra['twitter:description'] = $description;
        }

        if ($canonical !== '') {
            $extra['og:url'] = $canonical;
            $extra['twitter:url'] = $canonical;
        }

        $socialImage = $this->settings->siteSocialImage();
        if ($socialImage !== '') {
            $extra['og:image'] = $this->absoluteUrl($socialImage);
        }

        return [
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'canonical' => $canonical !== '' ? $canonical : null,
            'extra' => $extra,
            'structured_data' => [],
        ];
    }

    /**
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    private function normalizeMeta(array $meta): array
    {
        $title = isset($meta['title']) ? (string)$meta['title'] : (string)($this->defaultMeta['title'] ?? '');
        $description = isset($meta['description']) && $meta['description'] !== null
            ? (string)$meta['description']
            : '';

        $canonical = isset($meta['canonical']) ? $this->absoluteUrl((string)$meta['canonical']) : '';
        $meta['canonical'] = $canonical !== '' ? $canonical : null;

        $extra = is_array($meta['extra'] ?? null) ? $meta['extra'] : [];
        $extra = $this->ensureMetaValue($extra, 'og:title', $title);
        $extra = $this->ensureMetaValue($extra, 'twitter:title', $title);

        if ($description !== '') {
            $extra = $this->ensureMetaValue($extra, 'og:description', $description);
            $extra = $this->ensureMetaValue($extra, 'twitter:description', $description);
        }

        if ($canonical !== '') {
            $extra = $this->ensureMetaValue($extra, 'og:url', $canonical);
            $extra = $this->ensureMetaValue($extra, 'twitter:url', $canonical);
        }

        foreach (['og:image', 'twitter:image'] as $imageKey) {
            if (!array_key_exists($imageKey, $extra)) {
                continue;
            }
            $extra[$imageKey] = $this->normalizeMetaUrl($extra[$imageKey]);
        }

        $meta['extra'] = $extra;

        $structured = is_array($meta['structured_data'] ?? null) ? $meta['structured_data'] : [];
        $meta['structured_data'] = $this->mergeStructuredData([], $structured);

        return $meta;
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,mixed> $incoming
     * @return array<string,mixed>
     */
    private function mergeMetaExtra(array $base, array $incoming): array
    {
        return $base === [] && $incoming === []
            ? []
            : array_merge($base, $incoming);
    }

    /**
     * @param array<int|string,mixed> $base
     * @param array<int|string,mixed> $incoming
     * @return list<array<string,mixed>>
     */
    private function mergeStructuredData(array $base, array $incoming): array
    {
        $normalize = static function (array $items): array {
            $result = [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $result[] = $item;
            }
            return $result;
        };

        return array_values(array_merge($normalize($base), $normalize($incoming)));
    }

    /**
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private function ensureMetaValue(array $extra, string $key, string $value): array
    {
        if ($value === '') {
            return $extra;
        }

        if (!array_key_exists($key, $extra)) {
            $extra[$key] = $value;
            return $extra;
        }

        $current = $extra[$key];
        if (is_array($current)) {
            $content = isset($current['content']) ? (string)$current['content'] : '';
            if (trim($content) === '') {
                $extra[$key]['content'] = $value;
            }
            return $extra;
        }

        if (trim((string)$current) === '') {
            $extra[$key] = $value;
        }

        return $extra;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function normalizeMetaUrl(mixed $value): mixed
    {
        if (is_array($value)) {
            $normalized = $value;
            if (isset($value['content']) && is_string($value['content'])) {
                $normalized['content'] = $this->absoluteUrl($value['content']);
            }
            if (isset($value['url']) && is_string($value['url'])) {
                $normalized['url'] = $this->absoluteUrl($value['url']);
            }
            return $normalized;
        }

        if (!is_string($value)) {
            return $value;
        }

        $absolute = $this->absoluteUrl($value);
        return $absolute !== '' ? $absolute : $value;
    }

    private function absoluteUrl(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }
        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $trimmed;
        }
        $base = $this->settings->siteUrl();
        if ($base === '') {
            return $trimmed;
        }
        return rtrim($base, '/') . '/' . ltrim($trimmed, '/');
    }

    private function loadThemeFunctions(string $slug): void
    {
        $file = $this->baseDir . '/themes/' . $slug . '/functions.php';
        if (is_file($file)) {
            try {
                require_once $file;
            } catch (\Throwable $e) {
                error_log('Theme helper bootstrap failed: ' . $e->getMessage());
            }
        }
    }
}
