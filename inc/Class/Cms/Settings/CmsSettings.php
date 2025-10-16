<?php
declare(strict_types=1);

namespace Cms\Settings;

use Core\Database\Init as DB;

final class CmsSettings
{
    /** Cache jednoho řádku settings */
    private static ?array $row = null;

    private static function row(): array
    {
        if (self::$row === null) {
            $row = DB::query()->table('settings')->select(['*'])->where('id','=',1)->first();
            if (!$row) {
                // bezpečnostní defaulty (měly by existovat z migrací)
                $row = [
                    'site_title'  => 'Moje stránka',
                    'site_email'  => '',
                    'theme_slug'  => 'classic',
                    'date_format' => 'Y-m-d',
                    'time_format' => 'H:i',
                    'timezone'    => 'Europe/Prague',
                ];
            }
            self::$row = $row;
        }
        return self::$row;
    }

    /** Vyvolat po uložení nastavení, aby se promazal cache. */
    public static function refresh(): void
    {
        self::$row = null;
    }

    public function siteTitle(): string   { return (string)(self::row()['site_title'] ?? ''); }
    public function siteEmail(): string   { return (string)(self::row()['site_email'] ?? ''); }
    public function themeSlug(): string   { return (string)(self::row()['theme_slug'] ?? 'classic'); }
    public function dateFormat(): string  { return (string)(self::row()['date_format'] ?? 'Y-m-d'); }
    public function timeFormat(): string  { return (string)(self::row()['time_format'] ?? 'H:i'); }
    public function timezone(): string    { return (string)(self::row()['timezone'] ?? 'Europe/Prague'); }

    public function formatDate(\DateTimeInterface $dt): string
    {
        $tz = new \DateTimeZone($this->timezone());
        $d  = (new \DateTimeImmutable('@'.$dt->getTimestamp()))->setTimezone($tz);
        return $d->format($this->dateFormat());
    }

    public function formatTime(\DateTimeInterface $dt): string
    {
        $tz = new \DateTimeZone($this->timezone());
        $d  = (new \DateTimeImmutable('@'.$dt->getTimestamp()))->setTimezone($tz);
        return $d->format($this->timeFormat());
    }

    public function formatDateTime(\DateTimeInterface $dt, string $sep = ' '): string
    {
        $tz = new \DateTimeZone($this->timezone());
        $d  = (new \DateTimeImmutable('@'.$dt->getTimestamp()))->setTimezone($tz);
        return $d->format($this->dateFormat() . $sep . $this->timeFormat());
    }
}
