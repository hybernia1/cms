<?php
declare(strict_types=1);

namespace Cms\Utils;

use Cms\Settings\CmsSettings;
use DateTimeImmutable;
use DateTimeInterface;
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
            try {
                self::$timezone = new DateTimeZone($name);
                self::$timezoneName = $name;
            } catch (\Throwable) {
                self::$timezone = new DateTimeZone('Europe/Prague');
                self::$timezoneName = 'Europe/Prague';
            }
        }
        return self::$timezone;
    }

    private static function ensureTz(DateTimeInterface $dt): DateTimeImmutable
    {
        $imm = $dt instanceof DateTimeImmutable
            ? $dt
            : DateTimeImmutable::createFromInterface($dt);

        // sjednotíme na aplikační timezone
        return $imm->setTimezone(self::timezone());
    }

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', self::timezone());
    }

    public static function nowString(): string
    {
        return self::now()->format('Y-m-d H:i:s');
    }

    /**
     * Formát pro uložení do DB (stejný jako dřív).
     */
    public static function formatForStorage(DateTimeInterface $dateTime): string
    {
        return self::ensureTz($dateTime)->format('Y-m-d H:i:s');
    }

    /**
     * Načte hodnotu z DB a vrátí DateTimeImmutable v aplikačním timezone.
     * Podporuje "Y-m-d H:i:s", "Y-m-d", ISO8601 a unix timestamp.
     * Prázdné / nulové hodnoty vrací null.
     */
    public static function fromStorage(?string $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        $v = trim($value);
        if ($v === '' || $v === '0000-00-00' || $v === '0000-00-00 00:00:00') {
            return null;
        }

        // 1) čistý číselný timestamp
        if (ctype_digit($v)) {
            $dt = (new DateTimeImmutable('@' . $v))->setTimezone(self::timezone());
            return $dt;
        }

        // 2) Y-m-d H:i:s
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $v, self::timezone());
        if ($dt !== false) {
            return $dt;
        }

        // 3) Y-m-d
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $v, self::timezone());
        if ($dt !== false) {
            return $dt;
        }

        // 4) ISO 8601 / cokoliv, co PHP zvládne parsovat
        try {
            $dt = new DateTimeImmutable($v, self::timezone());
            return $dt;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Univerzální parser (můžeš používat i mimo DB).
     */
    public static function fromUserInput(string|int|DateTimeInterface|null $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return self::ensureTz($value);
        }

        if (is_int($value) || ctype_digit((string)$value)) {
            $dt = new DateTimeImmutable('@' . $value);
            return $dt->setTimezone(self::timezone());
        }

        return self::fromStorage((string)$value);
    }
}
