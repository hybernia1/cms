<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Settings;

use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Utils\DateTimeFactory;
use Cms\Admin\Utils\PermalinkSettings;
use Cms\Admin\Utils\SettingsPresets;
use Core\Database\Init as DB;

trait SettingsHelpers
{
    protected function loadSettings(): array
    {
        $row = DB::query()->table('settings')->select(['*'])->where('id', '=', 1)->first();
        if (!$row) {
            DB::query()->table('settings')->insertRow([
                'id'           => 1,
                'site_title'   => 'Moje stránka',
                'site_email'   => '',
                'theme_slug'   => 'classic',
                'date_format'  => 'Y-m-d',
                'time_format'  => 'H:i',
                'timezone'     => 'UTC+01:00',
                'allow_registration' => 1,
                'site_url'     => $this->detectSiteUrl(),
                'registration_auto_approve' => 1,
                'created_at'   => DateTimeFactory::nowString(),
                'updated_at'   => DateTimeFactory::nowString()
            ])->execute();
            $row = DB::query()->table('settings')->select(['*'])->where('id', '=', 1)->first();
        } else {
            $row['date_format']        = $row['date_format']        ?? 'Y-m-d';
            $row['time_format']        = $row['time_format']        ?? 'H:i';
            $row['timezone']           = $row['timezone']           ?? 'UTC+01:00';
            $row['allow_registration'] = isset($row['allow_registration']) ? (int)$row['allow_registration'] : 1;
            $row['registration_auto_approve'] = isset($row['registration_auto_approve']) ? (int)$row['registration_auto_approve'] : 1;
            $row['site_url']           = ($row['site_url'] ?? '') !== '' ? (string)$row['site_url'] : $this->detectSiteUrl();
        }

        $storedTimezone = (string)($row['timezone'] ?? '');

        $data = $this->decodeSettingsData($row['data'] ?? null);
        $media = is_array($data['media'] ?? null) ? $data['media'] : [];
        $row['webp_enabled'] = !empty($media['webp_enabled']) ? 1 : 0;
        $row['webp_compression'] = $this->normalizeWebpCompression((string)($media['webp_compression'] ?? ''));

        [$normalizedTz, $tzAdjusted] = $this->sanitizeTimezone($storedTimezone);
        $row['timezone'] = $normalizedTz;

        if ($tzAdjusted) {
            DB::query()->table('settings')->update([
                'timezone'   => $normalizedTz,
                'updated_at' => DateTimeFactory::nowString(),
            ])->where('id', '=', 1)->execute();
        }

        return is_array($row) ? $row : [];
    }

    protected function detectSiteUrl(): string
    {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                   || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
                   || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $scheme  = $isHttps ? 'https' : 'http';
        $host    = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $base    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        $path    = $base && $base !== '/' ? $base : '';

        return rtrim("{$scheme}://{$host}{$path}", '/');
    }

    protected function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (!preg_match('~^[a-z][a-z0-9+\-.]*://~i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }
        return rtrim($url, '/');
    }

    protected function timezones(): array
    {
        return SettingsPresets::timezones();
    }

    protected function formatPresets(): array
    {
        return [
            'date'     => SettingsPresets::dateFormats(),
            'time'     => SettingsPresets::timeFormats(),
            'datetime' => SettingsPresets::dateTimeFormats(),
        ];
    }

    protected function normalizeWebpCompression(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['high', 'medium', 'low'], true) ? $value : 'medium';
    }

    protected function normalizeMailDriver(string $driver): string
    {
        $driver = strtolower(trim($driver));
        return in_array($driver, ['php', 'smtp'], true) ? $driver : 'php';
    }

    protected function normalizeMailSecure(string $secure): string
    {
        $secure = strtolower(trim($secure));
        return in_array($secure, ['tls', 'ssl'], true) ? $secure : '';
    }

    protected function loadPermalinkSettings(): array
    {
        $data = $this->readSettingsData();
        $permalinks = is_array($data['permalinks'] ?? null) ? $data['permalinks'] : [];
        return PermalinkSettings::normalize($permalinks);
    }

    protected function readSettingsData(): array
    {
        $raw = DB::query()->table('settings')->select(['data'])->where('id', '=', 1)->value('data');
        return $this->decodeSettingsData($raw);
    }

    protected function decodeSettingsData(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    protected function sanitizeTimezone(string $timezone): array
    {
        $tz = trim($timezone);
        if ($tz === '') {
            $default = 'UTC+01:00';
            return [$default, true];
        }

        $normalized = SettingsPresets::normalizeTimezone($tz);

        try {
            $phpTimezone = SettingsPresets::toPhpTimezone($normalized);
            new \DateTimeZone($phpTimezone);
            return [$normalized, $normalized !== $tz];
        } catch (\Throwable) {
            $default = 'UTC+01:00';
            return [$default, true];
        }
    }

    protected function pickPresetValue(array $options, string $selected, string $default): string
    {
        $selected = trim($selected);
        if ($selected === '') {
            return $options[0] ?? $default;
        }
        foreach ($options as $option) {
            if (!is_string($option)) {
                continue;
            }
            if ($option === $selected) {
                return $option;
            }
        }
        return $selected;
    }
}
