<?php
declare(strict_types=1);

namespace Cms\Settings;

use Core\Database\Init as DB;
use Cms\Utils\SettingsPresets;

final class CmsSettings
{
    /** Cache jednoho řádku settings */
    private static ?array $row = null;
    private static ?array $dataCache = null;

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
                    'timezone'    => 'UTC+01:00',
                ];
            }
            $row['timezone'] = SettingsPresets::normalizeTimezone((string)($row['timezone'] ?? 'UTC+01:00'));
            self::$row = $row;
        }
        return self::$row;
    }

    private static function data(): array
    {
        if (self::$dataCache !== null) {
            return self::$dataCache;
        }
        $raw = self::row()['data'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                self::$dataCache = $decoded;
                return self::$dataCache;
            }
        }
        self::$dataCache = [];
        return self::$dataCache;
    }

    private static function mediaSettings(): array
    {
        $data = self::data();
        $media = $data['media'] ?? [];
        return is_array($media) ? $media : [];
    }

    private static function mailSettings(): array
    {
        $data = self::data();
        $mail = $data['mail'] ?? [];
        return is_array($mail) ? $mail : [];
    }

    /** Vyvolat po uložení nastavení, aby se promazal cache. */
    public static function refresh(): void
    {
        self::$row = null;
        self::$dataCache = null;
    }

    public function siteTitle(): string   { return (string)(self::row()['site_title'] ?? ''); }
    public function siteEmail(): string   { return (string)(self::row()['site_email'] ?? ''); }
    public function themeSlug(): string   { return (string)(self::row()['theme_slug'] ?? 'classic'); }
    public function dateFormat(): string  { return (string)(self::row()['date_format'] ?? 'Y-m-d'); }
    public function timeFormat(): string  { return (string)(self::row()['time_format'] ?? 'H:i'); }
    public function timezone(): string    { return SettingsPresets::normalizeTimezone((string)(self::row()['timezone'] ?? 'UTC+01:00')); }

    public function webpEnabled(): bool
    {
        $media = self::mediaSettings();
        return !empty($media['webp_enabled']);
    }

    public function webpCompression(): string
    {
        $media = self::mediaSettings();
        $value = isset($media['webp_compression']) ? (string)$media['webp_compression'] : 'medium';
        return in_array($value, ['high','medium','low'], true) ? $value : 'medium';
    }

    public function mailDriver(): string
    {
        $mail = self::mailSettings();
        $driver = is_string($mail['driver'] ?? null) ? strtolower((string)$mail['driver']) : 'php';
        return in_array($driver, ['php', 'smtp'], true) ? $driver : 'php';
    }

    public function mailFromEmail(): string
    {
        $mail = self::mailSettings();
        $from = $mail['from'] ?? [];
        if (is_array($from)) {
            $email = trim((string)($from['email'] ?? ''));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }
        return '';
    }

    public function mailFromName(): string
    {
        $mail = self::mailSettings();
        $from = $mail['from'] ?? [];
        $name = is_array($from) ? (string)($from['name'] ?? '') : '';
        return trim($name);
    }

    public function mailSignature(): string
    {
        $mail = self::mailSettings();
        return trim((string)($mail['signature'] ?? ''));
    }

    /**
     * @return array{host:string,port:int,username:string,password:string,secure:string}
     */
    public function mailSmtp(): array
    {
        $mail = self::mailSettings();
        $smtp = is_array($mail['smtp'] ?? null) ? $mail['smtp'] : [];

        $port = isset($smtp['port']) ? (int)$smtp['port'] : 587;
        if ($port <= 0 || $port > 65535) {
            $port = 587;
        }

        $secure = strtolower(trim((string)($smtp['secure'] ?? '')));
        if (!in_array($secure, ['tls', 'ssl'], true)) {
            $secure = '';
        }

        return [
            'host'     => (string)($smtp['host'] ?? ''),
            'port'     => $port,
            'username' => (string)($smtp['username'] ?? ''),
            'password' => (string)($smtp['password'] ?? ''),
            'secure'   => $secure,
        ];
    }

    public function formatDate(\DateTimeInterface $dt): string
    {
        $tz = $this->dateTimeZone();
        $d  = (new \DateTimeImmutable('@'.$dt->getTimestamp()))->setTimezone($tz);
        return $d->format($this->dateFormat());
    }

    public function formatTime(\DateTimeInterface $dt): string
    {
        $tz = $this->dateTimeZone();
        $d  = (new \DateTimeImmutable('@'.$dt->getTimestamp()))->setTimezone($tz);
        return $d->format($this->timeFormat());
    }

    public function formatDateTime(\DateTimeInterface $dt, string $sep = ' '): string
    {
        $tz = $this->dateTimeZone();
        $d  = (new \DateTimeImmutable('@'.$dt->getTimestamp()))->setTimezone($tz);
        return $d->format($this->dateFormat() . $sep . $this->timeFormat());
    }

    private function dateTimeZone(): \DateTimeZone
    {
        return new \DateTimeZone(SettingsPresets::toPhpTimezone($this->timezone()));
    }
}
