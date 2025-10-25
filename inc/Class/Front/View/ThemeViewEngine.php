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

            $baseExtra = [];
            if (isset($this->defaultMeta['extra']) && is_array($this->defaultMeta['extra'])) {
                $baseExtra = $this->defaultMeta['extra'];
            }
            $incomingExtra = [];
            if (isset($incoming['extra']) && is_array($incoming['extra'])) {
                $incomingExtra = $incoming['extra'];
            }
            $merged['extra'] = $baseExtra === [] && $incomingExtra === []
                ? []
                : array_merge($baseExtra, $incomingExtra);

            $data['meta'] = $merged;
        } else {
            $data['meta'] = $this->defaultMeta;
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
        ];
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
