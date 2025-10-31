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
    /** @var array<string,mixed> */
    private array $sharedData = [];
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

        $hierarchy = $this->resolveThemeHierarchy($trimmed);
        $paths = [];
        foreach ($hierarchy as $entry) {
            $path = $this->baseDir . '/themes/' . $entry['slug'] . '/templates';
            if (!is_dir($path)) {
                if ($entry['slug'] === $trimmed) {
                    throw MissingThemeException::forSlug($trimmed, $path);
                }
                continue;
            }
            $paths[] = $path;
        }

        if ($paths === []) {
            $fallback = $this->baseDir . '/themes/' . $trimmed . '/templates';
            throw MissingThemeException::forSlug($trimmed, $fallback);
        }

        $this->engine->setBasePaths($paths);
        $this->loadThemeManifest($trimmed);
        $this->shareThemeContext();
        $this->loadThemeFunctions($trimmed);
    }

    public function share(array $data): void
    {
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $this->sharedData[$key] = $value;
            }
        }
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
        $resolvedPayload = $this->resolvePayload($payload);

        try {
            if ($layout === null) {
                $this->engine->render($template, $payload);
                return;
            }

            $bufferLevel = ob_get_level();
            ob_start();
            try {
                $this->engine->render($layout, $payload, function () use ($template, $payload): void {
                    $this->engine->render($template, $payload);
                });
            } catch (Throwable $exception) {
                while (ob_get_level() > $bufferLevel) {
                    ob_end_clean();
                }
                throw $exception;
            }

            $html = ob_get_clean();
            $html = is_string($html) ? $html : '';
            echo $this->injectUserBar($html, $resolvedPayload);
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
        $this->themeInfo = $this->readThemeManifest($slug);
    }

    /**
     * @return array<string,mixed>
     */
    private function readThemeManifest(string $slug): array
    {
        $defaults = [
            'slug' => $slug,
            'name' => ucfirst($slug),
            'version' => '',
            'author' => '',
            'description' => '',
            'supports' => [],
            'palette' => [],
            'extends' => null,
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
        if (!isset($info['extends']) || !is_string($info['extends'])) {
            $info['extends'] = null;
        } else {
            $info['extends'] = trim($info['extends']);
            if ($info['extends'] === '') {
                $info['extends'] = null;
            }
        }

        unset($info['asset']);

        return $info;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function resolveThemeHierarchy(string $slug): array
    {
        $hierarchy = [];
        $current = $slug;
        $visited = [];

        while ($current !== '' && !in_array($current, $visited, true)) {
            $visited[] = $current;
            $manifest = $this->readThemeManifest($current);
            $hierarchy[] = $manifest;
            $parent = $manifest['extends'] ?? null;
            if (!is_string($parent) || $parent === '') {
                break;
            }
            $current = $parent;
        }

        return $hierarchy;
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
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function resolvePayload(array $data): array
    {
        if ($this->sharedData === []) {
            return $data;
        }

        return array_merge($this->sharedData, $data);
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

    /**
     * @param array<string,mixed> $payload
     */
    private function injectUserBar(string $html, array $payload): string
    {
        if (str_contains($html, 'cms-userbar')) {
            return $html;
        }

        $bar = $this->buildUserBar($payload);
        if ($bar === '') {
            return $html;
        }

        $html = $this->injectUserBarStyles($html);
        $html = $this->appendBodyClass($html, 'cms-has-userbar');

        return $this->insertAfterOpeningBody($html, $bar);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function buildUserBar(array $payload): string
    {
        $currentUser = isset($payload['currentUser']) && is_array($payload['currentUser'])
            ? $payload['currentUser']
            : null;

        if ($currentUser === null) {
            return '';
        }

        $notificationsRaw = $payload['notifications'] ?? [];
        $notifications = [];
        if (is_array($notificationsRaw)) {
            foreach ($notificationsRaw as $notice) {
                if (!is_array($notice)) {
                    continue;
                }
                $message = trim((string)($notice['message'] ?? ''));
                if ($message === '') {
                    continue;
                }
                $type = strtolower((string)($notice['type'] ?? 'info'));
                if (!in_array($type, ['info', 'success', 'warning', 'danger'], true)) {
                    $type = 'info';
                }
                $notifications[] = [
                    'type' => $type,
                    'message' => $message,
                ];
            }
        }

        $links = $payload['links'] ?? null;
        $linkGenerator = $links instanceof LinkGenerator ? $links : $this->links;

        $accountUrl = trim($linkGenerator->account());
        $adminUrl = trim($linkGenerator->admin());

        $profileUrl = '';
        $editUrl = $accountUrl;
        $avatarUrl = '';
        $displayName = 'Uživatel';
        $initial = '?';

        $displayName = trim((string)($currentUser['name'] ?? 'Uživatel')) ?: 'Uživatel';
        $profileUrl = isset($currentUser['profile_url']) ? trim((string)$currentUser['profile_url']) : '';
        $editCandidate = isset($currentUser['profile_edit_url']) ? trim((string)$currentUser['profile_edit_url']) : '';
        if ($editCandidate !== '') {
            $editUrl = $editCandidate;
        }
        $adminCandidate = isset($currentUser['admin_url']) ? trim((string)$currentUser['admin_url']) : '';
        if ($adminCandidate !== '') {
            $adminUrl = $adminCandidate;
        }
        $avatarUrl = isset($currentUser['avatar_url']) ? trim((string)$currentUser['avatar_url']) : '';
        $initial = $this->userBarInitial($displayName);

        ob_start();
        ?>
        <div class="cms-userbar" role="region" aria-label="Uživatelská lišta">
            <div class="cms-userbar__inner">
                <div class="cms-userbar__profile">
                    <div class="cms-userbar__avatar" aria-hidden="true">
                        <?php if ($avatarUrl !== ''): ?>
                            <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="36" height="36">
                        <?php else: ?>
                            <span><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="cms-userbar__meta">
                        <span class="cms-userbar__greeting">Přihlášen(a)</span>
                        <span class="cms-userbar__name"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
                <div class="cms-userbar__actions" role="navigation" aria-label="Rychlé odkazy">
                    <?php if ($profileUrl !== ''): ?>
                        <a class="cms-userbar__action" href="<?= htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Veřejný profil</a>
                    <?php endif; ?>
                    <?php if ($editUrl !== ''): ?>
                        <a class="cms-userbar__action" href="<?= htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8'); ?>">Upravit profil</a>
                    <?php endif; ?>
                    <?php if ($adminUrl !== ''): ?>
                        <a class="cms-userbar__action" href="<?= htmlspecialchars($adminUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Administrace</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($notifications !== []): ?>
                <div class="cms-userbar__notices" role="status" aria-live="polite">
                    <?php foreach ($notifications as $notice): ?>
                        <div class="cms-userbar__notice cms-userbar__notice--<?= htmlspecialchars($notice['type'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?= htmlspecialchars($notice['message'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        $output = ob_get_clean();

        return is_string($output) ? trim($output) : '';
    }

    private function userBarInitial(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return '?';
        }

        if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
            $initial = mb_substr($trimmed, 0, 1, 'UTF-8');
            return mb_strtoupper($initial, 'UTF-8');
        }

        $initial = substr($trimmed, 0, 1);
        return $initial !== false ? strtoupper($initial) : '?';
    }

    private function injectUserBarStyles(string $html): string
    {
        if (str_contains($html, 'cms-userbar__styles')) {
            return $html;
        }

        $styles = <<<'CSS'
<style id="cms-userbar__styles">
    body.cms-has-userbar {
        scroll-padding-top: 4rem;
    }
    .cms-userbar {
        position: sticky;
        top: 0;
        z-index: 1000;
        width: 100%;
        background: #111827;
        color: #f9fafb;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        font-size: 0.95rem;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.28);
    }
    .cms-userbar a {
        color: inherit;
        text-decoration: none;
    }
    .cms-userbar__inner {
        margin: 0 auto;
        padding: 0.6rem 1rem;
        max-width: 1200px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
    }
    .cms-userbar__profile {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        min-width: 0;
    }
    .cms-userbar__avatar {
        width: 36px;
        height: 36px;
        border-radius: 999px;
        background: rgba(248, 250, 252, 0.18);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        box-shadow: inset 0 0 0 1px rgba(248, 250, 252, 0.12);
    }
    .cms-userbar__avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .cms-userbar__avatar span {
        font-weight: 600;
        font-size: 0.95rem;
    }
    .cms-userbar__meta {
        display: flex;
        flex-direction: column;
        gap: 0.1rem;
    }
    .cms-userbar__greeting {
        font-size: 0.75rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: rgba(248, 250, 252, 0.7);
    }
    .cms-userbar__name {
        font-weight: 600;
        font-size: 1rem;
        color: #f9fafb;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 240px;
    }
    .cms-userbar__actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 0.5rem;
    }
    .cms-userbar__action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.35rem 0.85rem;
        border-radius: 999px;
        background: rgba(248, 250, 252, 0.14);
        font-weight: 600;
        font-size: 0.9rem;
        transition: background 0.2s ease, box-shadow 0.2s ease;
    }
    .cms-userbar__action:hover,
    .cms-userbar__action:focus {
        background: rgba(248, 250, 252, 0.24);
    }
    .cms-userbar__action:focus-visible {
        outline: 2px solid rgba(248, 250, 252, 0.95);
        outline-offset: 2px;
    }
    .cms-userbar__notices {
        margin: 0 auto;
        padding: 0.45rem 1rem 0.75rem;
        max-width: 1200px;
        display: grid;
        gap: 0.5rem;
    }
    .cms-userbar__notice {
        border-radius: 0.75rem;
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
        line-height: 1.4;
        box-shadow: inset 0 0 0 1px rgba(248, 250, 252, 0.18);
    }
    .cms-userbar__notice--success {
        background: rgba(16, 185, 129, 0.22);
        border: 1px solid rgba(16, 185, 129, 0.45);
    }
    .cms-userbar__notice--info {
        background: rgba(59, 130, 246, 0.18);
        border: 1px solid rgba(59, 130, 246, 0.4);
    }
    .cms-userbar__notice--warning {
        background: rgba(250, 204, 21, 0.28);
        border: 1px solid rgba(250, 204, 21, 0.45);
        color: #111827;
    }
    .cms-userbar__notice--danger {
        background: rgba(239, 68, 68, 0.22);
        border: 1px solid rgba(239, 68, 68, 0.5);
    }
    @media (max-width: 768px) {
        .cms-userbar__inner {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.9rem;
        }
        .cms-userbar__actions {
            justify-content: flex-start;
        }
        .cms-userbar__name {
            max-width: 100%;
        }
    }
</style>
CSS;

        $styles = trim($styles) . "\n";

        $pos = stripos($html, '</head>');
        if ($pos !== false) {
            return substr_replace($html, $styles . '</head>', $pos, 7);
        }

        return $styles . $html;
    }

    private function appendBodyClass(string $html, string $class): string
    {
        $normalized = preg_replace('~[^a-z0-9\-]~i', '', $class);
        if ($normalized === null || $normalized === '') {
            return $html;
        }

        $bodyStart = stripos($html, '<body');
        if ($bodyStart === false) {
            return $html;
        }

        $tagEnd = strpos($html, '>', $bodyStart);
        if ($tagEnd === false) {
            return $html;
        }

        $bodyTag = substr($html, $bodyStart, $tagEnd - $bodyStart + 1);

        $classPos = stripos($bodyTag, 'class=');
        if ($classPos !== false) {
            $quote = $bodyTag[$classPos + 6] ?? '';
            if ($quote === '"' || $quote === "'") {
                $valueStart = $classPos + 7;
                $valueEnd = strpos($bodyTag, $quote, $valueStart);
                if ($valueEnd !== false) {
                    $existing = substr($bodyTag, $valueStart, $valueEnd - $valueStart);
                    $parts = preg_split('/\s+/', $existing) ?: [];
                    if (!in_array($normalized, $parts, true)) {
                        $parts[] = $normalized;
                        $updated = implode(' ', array_filter($parts));
                        $bodyTag = substr($bodyTag, 0, $valueStart) . $updated . substr($bodyTag, $valueEnd);
                    }

                    return substr($html, 0, $bodyStart) . $bodyTag . substr($html, $tagEnd + 1);
                }
            }
        }

        $replacement = substr($bodyTag, 0, 5) . ' class="' . $normalized . '"' . substr($bodyTag, 5);
        return substr($html, 0, $bodyStart) . $replacement . substr($html, $tagEnd + 1);
    }

    private function insertAfterOpeningBody(string $html, string $insertion): string
    {
        if ($insertion === '') {
            return $html;
        }

        $bodyStart = stripos($html, '<body');
        if ($bodyStart === false) {
            return $insertion . $html;
        }

        $tagEnd = strpos($html, '>', $bodyStart);
        if ($tagEnd === false) {
            return $html;
        }

        $prefix = substr($html, 0, $tagEnd + 1);
        $suffix = substr($html, $tagEnd + 1);

        return $prefix . "\n" . $insertion . $suffix;
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
