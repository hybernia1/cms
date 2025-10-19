<?php
declare(strict_types=1);

namespace Cms\Admin\Utils;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Provides hardcoded preset lists for settings such as timezones
 * and date/time formats. Replaces the previous JSON based configuration
 * so the presets are always available even without external files.
 */
final class SettingsPresets
{
    /**
     * Source offsets for UTC-based timezone selection (minutes).
     *
     * @var array<int,int>
     */
    private const TIMEZONE_OFFSETS = [
        -720, -660, -600, -570, -540, -480, -420, -360, -300, -240, -210, -180,
        -120, -60, 0, 60, 120, 180, 210, 240, 270, 300, 330, 345, 360, 390, 420,
        480, 525, 540, 570, 600, 630, 660, 720, 765, 780, 840,
    ];

    /**
     * @return array<int,string>
     */
    public static function timezones(): array
    {
        return array_map([self::class, 'formatTimezoneOffset'], self::TIMEZONE_OFFSETS);
    }

    /**
     * Returns a human readable label for a timezone option.
     */
    public static function timezoneLabel(string $value): string
    {
        $normalized = self::normalizeTimezone($value);
        if (preg_match('/^UTC([+-])(\d{2}):(\d{2})$/', $normalized, $m)) {
            $sign = $m[1] === '-' ? '−' : '+';
            return sprintf('UTC %s%s:%s', $sign, $m[2], $m[3]);
        }
        return $normalized;
    }

    /**
     * Normalizes any timezone representation to one of the supported UTC offsets.
     */
    public static function normalizeTimezone(string $value): string
    {
        $value = strtoupper(trim($value));
        if ($value === '' || $value === 'UTC') {
            return 'UTC+00:00';
        }

        if (preg_match('/^UTC([+-])(\d{1,2})(?::?([0-5]\d))?$/', $value, $m)) {
            $hours = (int) $m[2];
            $minutes = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : 0;
            $totalMinutes = $hours * 60 + $minutes;
            $totalMinutes = $m[1] === '-' ? -$totalMinutes : $totalMinutes;
            $formatted = self::formatTimezoneOffset($totalMinutes);
            return in_array($formatted, self::timezones(), true) ? $formatted : 'UTC+00:00';
        }

        try {
            $tz = new DateTimeZone($value);
            $offsetSeconds = $tz->getOffset(new DateTimeImmutable('now', new DateTimeZone('UTC')));
            $minutes = (int) round($offsetSeconds / 60);
            $formatted = self::formatTimezoneOffset($minutes);
            if (in_array($formatted, self::timezones(), true)) {
                return $formatted;
            }
        } catch (\Throwable) {
            // fall through to default
        }

        return 'UTC+00:00';
    }

    /**
     * Converts a normalized timezone value into a representation accepted by PHP's DateTimeZone.
     *
     * @param string $value Normalized timezone (e.g. "UTC+02:00").
     */
    public static function toPhpTimezone(string $value): string
    {
        $normalized = self::normalizeTimezone($value);
        if (preg_match('/^UTC([+-])(\d{2}):(\d{2})$/', $normalized, $m)) {
            $sign = $m[1] === '-' ? '-' : '+';
            return sprintf('%s%s:%s', $sign, $m[2], $m[3]);
        }

        return $normalized;
    }

    private static function formatTimezoneOffset(int $minutes): string
    {
        $sign = $minutes >= 0 ? '+' : '-';
        $abs = abs($minutes);
        $hours = intdiv($abs, 60);
        $mins = $abs % 60;
        return sprintf('UTC%s%02d:%02d', $sign, $hours, $mins);
    }

    /**
     * @return array<int,string>
     */
    public static function dateFormats(): array
    {
        return [
            'Y-m-d',
            'd.m.Y',
            'j. n. Y',
            'd/m/Y',
            'm/d/Y',
            'j F Y',
        ];
    }

    /**
     * @return array<int,string>
     */
    public static function timeFormats(): array
    {
        return [
            'H:i',
            'H:i:s',
            'G:i',
            'g:i A',
            'g:i a',
        ];
    }

    /**
     * @return array<int,string>
     */
    public static function dateTimeFormats(): array
    {
        return [
            'Y-m-d H:i',
            'Y-m-d H:i:s',
            'd.m.Y H:i',
            'j. n. Y H:i',
            'd/m/Y H:i',
            'm/d/Y h:i A',
        ];
    }
}
