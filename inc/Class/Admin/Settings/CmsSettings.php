<?php
declare(strict_types=1);

namespace Cms\Admin\Settings;

use Core\Database\Init as DB;
use Cms\Admin\Utils\PermalinkSettings;
use Cms\Admin\Utils\SettingsPresets;
use Cms\Admin\Utils\UploadPathFactory;

final class CmsSettings
{
    /** Cache jednoho řádku settings */
    private static ?array $row = null;
    private static ?array $dataCache = null;
    private static ?array $permalinksCache = null;

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
                    'allow_registration' => 1,
                    'registration_auto_approve' => 1,
                ];
            }
            $row['allow_registration'] = isset($row['allow_registration']) ? (int)$row['allow_registration'] : 1;
            $row['registration_auto_approve'] = isset($row['registration_auto_approve']) ? (int)$row['registration_auto_approve'] : 1;
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

    private static function siteSettings(): array
    {
        $data = self::data();
        $site = $data['site'] ?? [];
        return is_array($site) ? $site : [];
    }

    /**
     * @return array{seo_urls:bool,post_base:string,page_base:string,tag_base:string,category_base:string}
     */
    private static function permalinkSettings(): array
    {
        if (self::$permalinksCache !== null) {
            return self::$permalinksCache;
        }

        $data = self::data();
        $raw = $data['permalinks'] ?? [];
        $permalinks = is_array($raw) ? $raw : [];

        self::$permalinksCache = PermalinkSettings::normalize($permalinks);

        return self::$permalinksCache;
    }

    /** Vyvolat po uložení nastavení, aby se promazal cache. */
    public static function refresh(): void
    {
        self::$row = null;
        self::$dataCache = null;
        self::$permalinksCache = null;
    }

    public function siteTitle(): string   { return (string)(self::row()['site_title'] ?? ''); }
    public function siteEmail(): string   { return (string)(self::row()['site_email'] ?? ''); }
    public function themeSlug(): string   { return (string)(self::row()['theme_slug'] ?? 'classic'); }
    public function siteUrl(): string
    {
        $raw = trim((string)(self::row()['site_url'] ?? ''));
        if ($raw !== '') {
            return rtrim($raw, '/');
        }

        return $this->detectSiteUrl();
    }
    public function siteTagline(): string
    {
        $site = self::siteSettings();
        $tagline = isset($site['tagline']) ? (string)$site['tagline'] : '';
        return trim($tagline);
    }
    public function siteLocale(): string
    {
        $site = self::siteSettings();
        $raw = trim((string)($site['locale'] ?? ''));
        if ($raw === '') {
            return 'cs';
        }
        $normalized = preg_replace('~[^a-zA-Z_\-]~', '', $raw) ?? '';
        $normalized = $normalized !== '' ? $normalized : 'cs';
        return str_replace('_', '-', $normalized);
    }
    public function siteSocialImage(): string
    {
        $site = self::siteSettings();
        $image = trim((string)($site['social_image'] ?? ''));
        return $image;
    }
    public function registrationAllowed(): bool
    {
        return (int)(self::row()['allow_registration'] ?? 1) === 1;
    }
    public function registrationAutoApprove(): bool
    {
        return (int)(self::row()['registration_auto_approve'] ?? 1) === 1;
    }
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

    public function siteFavicon(): string
    {
        $media = self::mediaSettings();
        $favicon = is_array($media['favicon'] ?? null) ? $media['favicon'] : [];
        $relative = isset($favicon['relative']) ? trim((string)$favicon['relative']) : '';
        if ($relative !== '') {
            try {
                return UploadPathFactory::forUploads()->publicUrl($relative);
            } catch (\Throwable) {
                // fallback to stored URL if resolver fails
            }
        }

        $url = isset($favicon['url']) ? trim((string)$favicon['url']) : '';
        return $url;
    }

    public function seoUrlsEnabled(): bool
    {
        $permalinks = self::permalinkSettings();
        return (bool)$permalinks['seo_urls'];
    }

    /**
     * @return array{post_base:string,page_base:string,tag_base:string,category_base:string}
     */
    public function permalinkBases(): array
    {
        $permalinks = self::permalinkSettings();

        return [
            'post_base'     => $permalinks['post_base'],
            'page_base'     => $permalinks['page_base'],
            'tag_base'      => $permalinks['tag_base'],
            'category_base' => $permalinks['category_base'],
        ];
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

    private function detectSiteUrl(): string
    {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $scheme = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        $path = $base && $base !== '/' ? $base : '';

        return rtrim("{$scheme}://{$host}{$path}", '/');
    }
}
