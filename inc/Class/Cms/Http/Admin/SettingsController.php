<?php
declare(strict_types=1);

namespace Cms\Http\Admin;

use Core\Database\Init as DB;
use Cms\Mail\MailService;
use Cms\Settings\CmsSettings;
use Cms\Utils\AdminNavigation;
use Cms\Utils\DateTimeFactory;
use Cms\Utils\SettingsPresets;

final class SettingsController extends BaseAdminController
{

    public function handle(string $action): void
    {
        switch ($action) {
            case 'mail':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $intent = (string)($_POST['intent'] ?? 'save');
                    if ($intent === 'test') { $this->sendTestMail(); return; }
                    $this->saveMail(); return;
                }
                $this->mail(); return;
            case 'index':
            default:
                if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->save(); return; }
                $this->index(); return;
        }
    }

    private function loadSettings(): array
    {
        // 1 řádek v tabulce settings (id=1)
        $row = DB::query()->table('settings')->select(['*'])->where('id','=',1)->first();
        if (!$row) {
            DB::query()->table('settings')->insertRow([
                'id'           => 1,
                'site_title'   => 'Moje stránka',
                'site_email'   => '',
                'theme_slug'   => 'classic',
                'date_format'  => 'Y-m-d',
                'time_format'  => 'H:i',
                'timezone'     => 'Europe/Prague',
                'allow_registration' => 1,
                'site_url'     => $this->detectSiteUrl(),
                'created_at'   => DateTimeFactory::nowString(),
                'updated_at'   => DateTimeFactory::nowString()
            ])->execute();
            $row = DB::query()->table('settings')->select(['*'])->where('id','=',1)->first();
        } else {
            // doplň chybějící klíče bezpečnými defaulty (když migrace neběžela apod.)
            $row['date_format']        = $row['date_format']        ?? 'Y-m-d';
            $row['time_format']        = $row['time_format']        ?? 'H:i';
            $row['timezone']           = $row['timezone']           ?? 'Europe/Prague';
            $row['allow_registration'] = isset($row['allow_registration']) ? (int)$row['allow_registration'] : 1;
            $row['site_url']           = ($row['site_url'] ?? '') !== '' ? (string)$row['site_url'] : $this->detectSiteUrl();
        }

        $data = $this->decodeSettingsData($row['data'] ?? null);
        $media = is_array($data['media'] ?? null) ? $data['media'] : [];
        $row['webp_enabled'] = !empty($media['webp_enabled']) ? 1 : 0;
        $row['webp_compression'] = $this->normalizeWebpCompression((string)($media['webp_compression'] ?? ''));

        return $row ?? [];
    }

    private function timezones(): array
    {
        $presets = SettingsPresets::timezones();
        return $presets !== [] ? $presets : \DateTimeZone::listIdentifiers();
    }

    private function formatPresets(): array
    {
        return [
            'date'     => SettingsPresets::dateFormats(),
            'time'     => SettingsPresets::timeFormats(),
            'datetime' => SettingsPresets::dateTimeFormats(),
        ];
    }

    private function decodeSettingsData($raw): array
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

    private function readSettingsData(): array
    {
        $raw = DB::query()->table('settings')->select(['data'])->where('id','=',1)->value('data');
        return $this->decodeSettingsData($raw);
    }

    private function loadMailSettings(): array
    {
        $data = $this->readSettingsData();
        $mail = is_array($data['mail'] ?? null) ? $data['mail'] : [];
        $from = is_array($mail['from'] ?? null) ? $mail['from'] : [];
        $smtp = is_array($mail['smtp'] ?? null) ? $mail['smtp'] : [];

        $driver = $this->normalizeMailDriver((string)($mail['driver'] ?? 'php'));
        $signature = (string)($mail['signature'] ?? '');

        $port = isset($smtp['port']) ? (int)$smtp['port'] : 587;
        if ($port <= 0 || $port > 65535) {
            $port = 587;
        }

        return [
            'driver'        => $driver,
            'from_email'    => (string)($from['email'] ?? ''),
            'from_name'     => (string)($from['name'] ?? ''),
            'signature'     => $signature,
            'smtp_host'     => (string)($smtp['host'] ?? ''),
            'smtp_port'     => $port,
            'smtp_username' => (string)($smtp['username'] ?? ''),
            'smtp_password' => (string)($smtp['password'] ?? ''),
            'smtp_secure'   => $this->normalizeMailSecure((string)($smtp['secure'] ?? '')),
        ];
    }

    private function normalizeMailDriver(string $driver): string
    {
        $driver = strtolower(trim($driver));
        return in_array($driver, ['php','smtp'], true) ? $driver : 'php';
    }

    private function normalizeMailSecure(string $secure): string
    {
        $secure = strtolower(trim($secure));
        return in_array($secure, ['tls','ssl'], true) ? $secure : '';
    }

    private function normalizeWebpCompression(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['high','medium','low'], true) ? $value : 'medium';
    }

    private function index(): void
    {
        $settings = $this->loadSettings();
        $tz = new \DateTimeZone((string)($settings['timezone'] ?? 'Europe/Prague'));
        $now = (new \DateTimeImmutable('now', $tz));
        $dateFmt = (string)($settings['date_format'] ?? 'Y-m-d');
        $timeFmt = (string)($settings['time_format'] ?? 'H:i');

        $this->renderAdmin('settings/index', [
            'pageTitle'     => 'Nastavení',
            'nav'           => AdminNavigation::build('settings:general'),
            'settings'      => $settings,
            'timezones'     => $this->timezones(),
            'previewNow'    => $now->format($dateFmt.' '.$timeFmt),
            'formatPresets' => $this->formatPresets(),
        ]);
    }

    private function mail(): void
    {
        $mailSettings = $this->loadMailSettings();

        $generalSettings = $this->loadSettings();

        $this->renderAdmin('settings/mail', [
            'pageTitle' => 'Nastavení e-mailu',
            'nav'       => AdminNavigation::build('settings:mail'),
            'mail'      => $mailSettings,
            'drivers'   => [
                'php'  => 'PHP mail()',
                'smtp' => 'SMTP server',
            ],
            'siteEmail' => (string)($generalSettings['site_email'] ?? ''),
            'siteName'  => (string)($generalSettings['site_title'] ?? ''),
        ]);
    }

    private function detectSiteUrl(): string
    {
        // jednoduchá autodetekce podle aktuálního requestu
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                   || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
                   || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $scheme  = $isHttps ? 'https' : 'http';
        $host    = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $base    = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        $path    = $base && $base !== '/' ? $base : '';
        return rtrim("{$scheme}://{$host}{$path}", '/');
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';
        // doplň schéma, pokud chybí
        if (!preg_match('~^[a-z][a-z0-9+\-.]*://~i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }
        // osekej trailing slash (bezpečně pro root)
        $url = rtrim($url, '/');
        return $url;
    }

    private function save(): void
    {
        $this->assertCsrf();

        $title = trim((string)($_POST['site_title'] ?? ''));
        $email = trim((string)($_POST['site_email'] ?? ''));

        $formatPresets = $this->formatPresets();
        $dateOptions = is_array($formatPresets['date'] ?? null) ? $formatPresets['date'] : [];
        $timeOptions = is_array($formatPresets['time'] ?? null) ? $formatPresets['time'] : [];

        $dateFormat = $this->pickPresetValue($dateOptions, (string)($_POST['date_format'] ?? ''), 'Y-m-d');
        $timeFormat = $this->pickPresetValue($timeOptions, (string)($_POST['time_format'] ?? ''), 'H:i');

        $tz = (string)($_POST['timezone'] ?? 'Europe/Prague');
        $tzList = $this->timezones();
        if (!in_array($tz, $tzList, true)) {
            $tz = 'Europe/Prague';
        }

        // nové: allow_registration + site_url
        $allowReg = (int)($_POST['allow_registration'] ?? 0) === 1 ? 1 : 0;

        $siteUrlIn = trim((string)($_POST['site_url'] ?? ''));
        if ($siteUrlIn === '') {
            $siteUrl = $this->detectSiteUrl();
        } else {
            $siteUrl = $this->normalizeUrl($siteUrlIn);
            // minimální validace hostu
            if (filter_var($siteUrl, FILTER_VALIDATE_URL) === false) {
                // fallback na autodetekci při neplatném vstupu
                $siteUrl = $this->detectSiteUrl();
            }
        }

        $webpEnabled = (int)($_POST['webp_enabled'] ?? 0) === 1;
        $webpCompression = $this->normalizeWebpCompression((string)($_POST['webp_compression'] ?? ''));

        $data = $this->readSettingsData();
        if (!isset($data['media']) || !is_array($data['media'])) {
            $data['media'] = [];
        }
        $data['media']['webp_enabled'] = $webpEnabled;
        $data['media']['webp_compression'] = $webpCompression;

        $dataJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($dataJson === false) {
            $dataJson = '{}';
        }

        DB::query()->table('settings')->update([
            'site_title'         => $title !== '' ? $title : 'Moje stránka',
            'site_email'         => $email,
            'date_format'        => $dateFormat,
            'time_format'        => $timeFormat,
            'timezone'           => $tz,
            'allow_registration' => $allowReg,
            'site_url'           => $siteUrl,
            'data'               => $dataJson,
            'updated_at'         => DateTimeFactory::nowString(),
        ])->where('id','=',1)->execute();

        // promaž cache settings
        CmsSettings::refresh();

        $this->flash('success','Nastavení uloženo.');
        $this->redirect('admin.php?r=settings');
    }

    private function saveMail(): void
    {
        $this->assertCsrf();

        $driver = $this->normalizeMailDriver((string)($_POST['mail_driver'] ?? 'php'));
        $fromEmail = trim((string)($_POST['mail_from_email'] ?? ''));
        if ($fromEmail !== '' && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $fromEmail = '';
        }
        $fromName = trim((string)($_POST['mail_from_name'] ?? ''));
        $signature = trim((string)($_POST['mail_signature'] ?? ''));

        $smtpHost = trim((string)($_POST['mail_smtp_host'] ?? ''));
        $smtpPort = (int)($_POST['mail_smtp_port'] ?? 587);
        if ($smtpPort <= 0 || $smtpPort > 65535) {
            $smtpPort = 587;
        }
        $smtpUsername = trim((string)($_POST['mail_smtp_username'] ?? ''));
        $smtpPassword = (string)($_POST['mail_smtp_password'] ?? '');
        $smtpSecure = $this->normalizeMailSecure((string)($_POST['mail_smtp_secure'] ?? ''));

        $data = $this->readSettingsData();
        if (!isset($data['mail']) || !is_array($data['mail'])) {
            $data['mail'] = [];
        }

        $data['mail']['driver'] = $driver;
        $data['mail']['signature'] = $signature;
        $data['mail']['from'] = [
            'email' => $fromEmail,
            'name'  => $fromName,
        ];
        $data['mail']['smtp'] = [
            'host'     => $smtpHost,
            'port'     => $smtpPort,
            'username' => $smtpUsername,
            'password' => $smtpPassword,
            'secure'   => $smtpSecure,
        ];

        $dataJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($dataJson === false) {
            $dataJson = '{}';
        }

        DB::query()->table('settings')->update([
            'data'       => $dataJson,
            'updated_at' => DateTimeFactory::nowString(),
        ])->where('id','=',1)->execute();

        CmsSettings::refresh();

        $this->flash('success','E-mailové nastavení bylo uloženo.');
        $this->redirect('admin.php?r=settings&a=mail');
    }

    private function sendTestMail(): void
    {
        $this->assertCsrf();

        $email = trim((string)($_POST['test_email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('danger','Zadejte platnou e-mailovou adresu pro test.');
            $this->redirect('admin.php?r=settings&a=mail');
        }

        $mailService = new MailService(new CmsSettings());
        $subject = 'Testovací e-mail';
        $body = '<p>Toto je testovací e-mail z redakčního systému.</p>';
        $ok = $mailService->send($email, $subject, $body);

        if ($ok) {
            $this->flash('success', sprintf('Testovací e-mail byl odeslán na %s.', $email));
        } else {
            $this->flash('danger','Testovací e-mail se nepodařilo odeslat. Zkontrolujte nastavení serveru.');
        }

        $this->redirect('admin.php?r=settings&a=mail');
    }

    private function pickPresetValue(array $options, string $selected, string $default): string
    {
        $selected = trim($selected);
        foreach ($options as $option) {
            if (!is_string($option)) {
                continue;
            }
            if ($option === $selected) {
                return $option;
            }
        }
        return $options[0] ?? $default;
    }
}
