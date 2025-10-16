<?php
declare(strict_types=1);

namespace Cms\Utils;

/**
 * Provides hardcoded preset lists for settings such as timezones
 * and date/time formats. Replaces the previous JSON based configuration
 * so the presets are always available even without external files.
 */
final class SettingsPresets
{
    /**
     * @return array<int,string>
     */
    public static function timezones(): array
    {
        return [
            'Europe/Prague',
            'Europe/Bratislava',
            'Europe/Vienna',
            'Europe/Berlin',
            'Europe/Paris',
            'Europe/Madrid',
            'Europe/London',
            'Europe/Warsaw',
            'Europe/Budapest',
            'Europe/Rome',
            'America/New_York',
            'America/Chicago',
            'America/Los_Angeles',
            'America/Sao_Paulo',
            'America/Mexico_City',
            'Asia/Tokyo',
            'Asia/Shanghai',
            'Asia/Hong_Kong',
            'Asia/Singapore',
            'Asia/Kolkata',
            'Asia/Dubai',
            'Australia/Sydney',
            'Pacific/Auckland',
            'UTC',
        ];
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
