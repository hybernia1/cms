<?php
declare(strict_types=1);

namespace Cms\Utils;

use Cms\Settings\CmsSettings;
use DateTimeImmutable;
use DateTimeZone;

final class DateTimeFactory
{
    private static ?DateTimeZone $timezone = null;
    private static ?string $timezoneName = null;

    private static function timezone(): DateTimeZone
    {
        $settings = new CmsSettings();
        $name = $settings->timezone();
        if (self::$timezone === null || self::$timezoneName !== $name) {
            self::$timezone = new DateTimeZone($name);
            self::$timezoneName = $name;
        }
        return self::$timezone;
    }

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', self::timezone());
    }

    public static function nowString(): string
    {
        return self::now()->format('Y-m-d H:i:s');
    }

    public static function formatForStorage(\DateTimeInterface $dateTime): string
    {
        $dt = $dateTime;
        if (!$dateTime instanceof DateTimeImmutable) {
            $dt = DateTimeImmutable::createFromInterface($dateTime);
        }
        return $dt->setTimezone(self::timezone())->format('Y-m-d H:i:s');
    }

    public static function fromStorage(?string $value): ?DateTimeImmutable
    {
        $value = $value === null ? null : trim($value);
        if ($value === null || $value === '') {
            return null;
        }

        $tz = self::timezone();
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $tz);
        if ($dt instanceof DateTimeImmutable) {
            return $dt;
        }

        try {
            return new DateTimeImmutable($value, $tz);
        } catch (\Throwable) {
            return null;
        }
    }
}
