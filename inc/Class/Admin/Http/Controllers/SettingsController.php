<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Controllers;

use Core\Database\Init as DB;
use Core\Files\PathResolver;
use Core\Files\Uploader;
use Cms\Admin\Mail\MailService;
use Cms\Admin\Mail\TemplateManager;
use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Utils\AdminNavigation;
use Cms\Admin\Utils\DateTimeFactory;
use Cms\Admin\Utils\PermalinkSettings;
use Cms\Admin\Utils\SettingsPresets;

final class SettingsController extends BaseAdminController
{

    public function handle(string $action): void
    {
        switch ($action) {
            case 'permalinks':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->savePermalinks(); return; }
                $this->permalinks(); return;
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
                'timezone'     => 'UTC+01:00',
                'allow_registration' => 1,
                'site_url'     => $this->detectSiteUrl(),
                'registration_auto_approve' => 1,
                'created_at'   => DateTimeFactory::nowString(),
                'updated_at'   => DateTimeFactory::nowString()
            ])->execute();
            $row = DB::query()->table('settings')->select(['*'])->where('id','=',1)->first();
        } else {
            // doplň chybějící klíče bezpečnými defaulty (když migrace neběžela apod.)
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

        $favicon = is_array($media['favicon'] ?? null) ? $media['favicon'] : [];
        $faviconRelative = isset($favicon['relative']) ? (string)$favicon['relative'] : '';
        $faviconUrl = '';
        if ($faviconRelative !== '') {
            try {
                $faviconUrl = $this->uploadPaths()->publicUrl($faviconRelative);
            } catch (\Throwable) {
                $faviconUrl = isset($favicon['url']) ? (string)$favicon['url'] : '';
            }
        } elseif (!empty($favicon['url'])) {
            $faviconUrl = (string)$favicon['url'];
        }

        $row['favicon_relative'] = $faviconRelative;
        $row['favicon_url'] = $faviconUrl;
        $row['favicon_mime'] = isset($favicon['mime']) ? (string)$favicon['mime'] : '';

        [$normalizedTz, $tzAdjusted] = $this->sanitizeTimezone($storedTimezone);
        $row['timezone'] = $normalizedTz;

        if ($tzAdjusted) {
            DB::query()->table('settings')->update([
                'timezone'   => $normalizedTz,
                'updated_at' => DateTimeFactory::nowString(),
            ])->where('id','=',1)->execute();
        }

        return $row ?? [];
    }

    private function timezones(): array
    {
        return SettingsPresets::timezones();
    }

    private function formatPresets(): array
    {
        return [
            'date'     => SettingsPresets::dateFormats(),
            'time'     => SettingsPresets::timeFormats(),
            'datetime' => SettingsPresets::dateTimeFormats(),
        ];
    }

    /**
     * @return array{0:string,1:bool} [normalized timezone, wasAdjusted]
     */
    private function sanitizeTimezone(string $timezone): array
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

    /**
     * @return array{seo_urls:bool,post_base:string,page_base:string,tag_base:string,category_base:string}
     */
    private function loadPermalinkSettings(): array
    {
        $data = $this->readSettingsData();
        $permalinks = is_array($data['permalinks'] ?? null) ? $data['permalinks'] : [];
        return PermalinkSettings::normalize($permalinks);
    }

    private function index(): void
    {
        $settings = $this->loadSettings();
        $timezone = SettingsPresets::normalizeTimezone((string)($settings['timezone'] ?? 'UTC+01:00'));
        $settings['timezone'] = $timezone;
        $phpTimezone = SettingsPresets::toPhpTimezone($timezone);
        $tz = new \DateTimeZone($phpTimezone);
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

    private function permalinks(): void
    {
        $permalinks = $this->loadPermalinkSettings();

        $this->renderAdmin('settings/permalinks', [
            'pageTitle'  => 'Trvalé odkazy',
            'nav'        => AdminNavigation::build('settings:permalinks'),
            'permalinks' => $permalinks,
            'defaults'   => PermalinkSettings::defaults(),
        ]);
    }

    private function savePermalinks(): void
    {
        $this->assertCsrf();

        $input = [
            'seo_urls'      => (int)($_POST['seo_urls_enabled'] ?? 0) === 1,
            'post_base'     => (string)($_POST['post_base'] ?? ''),
            'page_base'     => (string)($_POST['page_base'] ?? ''),
            'tag_base'      => (string)($_POST['tag_base'] ?? ''),
            'category_base' => (string)($_POST['category_base'] ?? ''),
        ];

        $errors = $this->validatePermalinkInput($input);
        if ($errors !== []) {
            $errors['form'][] = 'Opravte zvýrazněné chyby.';
            $this->respondSettingsError(
                'Trvalé odkazy nebyly uloženy.',
                $errors,
                422,
                'admin.php?r=settings&a=permalinks'
            );
        }

        $permalinks = PermalinkSettings::normalize($input);

        $data = $this->readSettingsData();
        $data['permalinks'] = $permalinks;

        $dataJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($dataJson === false) {
            $dataJson = '{}';
        }

        DB::query()->table('settings')->update([
            'data'       => $dataJson,
            'updated_at' => DateTimeFactory::nowString(),
        ])->where('id','=',1)->execute();

        CmsSettings::refresh();

        $this->respondSettingsSuccess('Trvalé odkazy byly uloženy.', 'admin.php?r=settings&a=permalinks');
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

        $currentSettings = $this->loadSettings();

        $title = trim((string)($_POST['site_title'] ?? ''));
        $email = trim((string)($_POST['site_email'] ?? ''));
        $errors = [];

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['site_email'][] = 'Zadejte platnou e-mailovou adresu.';
        }

        $formatPresets = $this->formatPresets();
        $dateOptions = is_array($formatPresets['date'] ?? null) ? $formatPresets['date'] : [];
        $timeOptions = is_array($formatPresets['time'] ?? null) ? $formatPresets['time'] : [];

        $dateFormat = $this->pickPresetValue($dateOptions, (string)($_POST['date_format'] ?? ''), 'Y-m-d');
        $timeFormat = $this->pickPresetValue($timeOptions, (string)($_POST['time_format'] ?? ''), 'H:i');

        $tzInput = (string)($_POST['timezone'] ?? 'UTC+01:00');
        $tz = SettingsPresets::normalizeTimezone($tzInput);
        $tzList = $this->timezones();
        if (!in_array($tz, $tzList, true)) {
            $errors['timezone'][] = 'Vyberte platnou časovou zónu.';
        }

        // nové: allow_registration + site_url
        $allowReg = (int)($_POST['allow_registration'] ?? 0) === 1 ? 1 : 0;
        $autoApproveInput = (int)($_POST['registration_auto_approve'] ?? (int)($currentSettings['registration_auto_approve'] ?? 1));
        $autoApprove = $autoApproveInput === 1 ? 1 : 0;
        if ($allowReg !== 1) {
            $autoApprove = isset($currentSettings['registration_auto_approve'])
                ? (int)$currentSettings['registration_auto_approve']
                : 1;
        }

        $siteUrlIn = trim((string)($_POST['site_url'] ?? ''));
        if ($siteUrlIn === '') {
            $siteUrl = $this->detectSiteUrl();
        } else {
            $siteUrl = $this->normalizeUrl($siteUrlIn);
            if (filter_var($siteUrl, FILTER_VALIDATE_URL) === false) {
                $errors['site_url'][] = 'Zadejte platnou URL adresu.';
            }
        }

        $webpEnabled = (int)($_POST['webp_enabled'] ?? 0) === 1;
        $webpCompression = $this->normalizeWebpCompression((string)($_POST['webp_compression'] ?? ''));
        $faviconRemove = (int)($_POST['favicon_remove'] ?? 0) === 1;
        $hasFaviconUpload = $this->hasUploadedFile('favicon');

        $faviconUpload = null;
        $uploadPaths = null;
        if ($hasFaviconUpload && $errors === []) {
            try {
                $uploadPaths = $this->uploadPaths();
                $uploader = new Uploader($uploadPaths, $this->faviconMimeWhitelist(), 2000000);
                $faviconUpload = $uploader->handle($_FILES['favicon'], 'settings');
            } catch (\Throwable) {
                $errors['favicon'][] = 'Soubor se nepodařilo nahrát. Zkontrolujte formát a velikost.';
            }
        }

        if ($errors !== []) {
            $errors['form'][] = 'Opravte zvýrazněné chyby.';
            $this->respondSettingsError('Nastavení se nepodařilo uložit.', $errors, 422, 'admin.php?r=settings');
        }

        if (!in_array($tz, $tzList, true)) {
            $tz = 'UTC+00:00';
        }

        $data = $this->readSettingsData();
        if (!isset($data['media']) || !is_array($data['media'])) {
            $data['media'] = [];
        }
        $existingFavicon = is_array($data['media']['favicon'] ?? null) ? $data['media']['favicon'] : [];
        $existingFaviconRel = isset($existingFavicon['relative']) ? (string)$existingFavicon['relative'] : '';

        if ($uploadPaths === null) {
            $uploadPaths = $this->uploadPaths();
        }

        if ($faviconUpload !== null) {
            if ($existingFaviconRel !== '') {
                $this->removeFaviconFile($existingFavicon, $uploadPaths);
            }
            $data['media']['favicon'] = [
                'relative' => $faviconUpload['relative'],
                'url'      => $faviconUpload['url'],
                'mime'     => $faviconUpload['mime'],
            ];
        } elseif ($faviconRemove && $existingFaviconRel !== '') {
            $this->removeFaviconFile($existingFavicon, $uploadPaths);
            unset($data['media']['favicon']);
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
            'registration_auto_approve' => $autoApprove,
            'site_url'           => $siteUrl,
            'data'               => $dataJson,
            'updated_at'         => DateTimeFactory::nowString(),
        ])->where('id','=',1)->execute();

        // promaž cache settings
        CmsSettings::refresh();

        $this->respondSettingsSuccess('Nastavení uloženo.', 'admin.php?r=settings');
    }

    private function hasUploadedFile(string $field): bool
    {
        if (empty($_FILES[$field]) || !is_array($_FILES[$field])) {
            return false;
        }

        $error = (int)($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE);

        return $error !== UPLOAD_ERR_NO_FILE;
    }

    private function faviconMimeWhitelist(): array
    {
        return [
            'image/png',
            'image/jpeg',
            'image/gif',
            'image/webp',
            'image/avif',
            'image/svg+xml',
            'image/x-icon',
            'image/vnd.microsoft.icon',
        ];
    }

    private function removeFaviconFile(array $favicon, PathResolver $paths): void
    {
        $relative = isset($favicon['relative']) ? (string)$favicon['relative'] : '';
        if ($relative === '') {
            return;
        }

        try {
            $absolute = $paths->absoluteFromRelative($relative);
        } catch (\Throwable) {
            return;
        }

        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    private function saveMail(): void
    {
        $this->assertCsrf();

        $driver = $this->normalizeMailDriver((string)($_POST['mail_driver'] ?? 'php'));
        $fromEmail = trim((string)($_POST['mail_from_email'] ?? ''));
        $errors = [];
        if ($fromEmail !== '' && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['mail_from_email'][] = 'Zadejte platnou e-mailovou adresu.';
        }
        $fromName = trim((string)($_POST['mail_from_name'] ?? ''));
        $signature = trim((string)($_POST['mail_signature'] ?? ''));

        $smtpHost = trim((string)($_POST['mail_smtp_host'] ?? ''));
        $smtpPort = (int)($_POST['mail_smtp_port'] ?? 587);
        if ($smtpPort <= 0 || $smtpPort > 65535) {
            $errors['mail_smtp_port'][] = 'Zadejte platné číslo portu (1-65535).';
        }
        $smtpUsername = trim((string)($_POST['mail_smtp_username'] ?? ''));
        $smtpPassword = (string)($_POST['mail_smtp_password'] ?? '');
        $smtpSecure = $this->normalizeMailSecure((string)($_POST['mail_smtp_secure'] ?? ''));

        if ($driver === 'smtp' && $smtpHost === '') {
            $errors['mail_smtp_host'][] = 'Vyplňte adresu SMTP serveru.';
        }

        if ($errors !== []) {
            $errors['form'][] = 'Opravte zvýrazněné chyby.';
            $this->respondSettingsError('E-mailové nastavení nebylo uloženo.', $errors, 422, 'admin.php?r=settings&a=mail');
        }

        if ($smtpPort <= 0 || $smtpPort > 65535) {
            $smtpPort = 587;
        }

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

        $this->respondSettingsSuccess('E-mailové nastavení bylo uloženo.', 'admin.php?r=settings&a=mail');
    }

    private function sendTestMail(): void
    {
        $this->assertCsrf();

        $email = trim((string)($_POST['test_email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors = [
                'test_email' => ['Zadejte platnou e-mailovou adresu.'],
                'form'       => ['Opravte zvýrazněné chyby.'],
            ];
            $this->respondSettingsError('Testovací e-mail nebyl odeslán.', $errors, 422, 'admin.php?r=settings&a=mail');
        }

        $settings = new CmsSettings();
        $mailService = new MailService($settings);
        $template = (new TemplateManager())->render('test_message', [
            'siteTitle' => $settings->siteTitle(),
        ]);
        $ok = $mailService->sendTemplate($email, $template);

        if ($ok) {
            $this->respondSettingsSuccess(
                sprintf('Testovací e-mail byl odeslán na %s.', $email),
                'admin.php?r=settings&a=mail'
            );
        }

        $this->respondSettingsError(
            'Testovací e-mail se nepodařilo odeslat. Zkontrolujte nastavení serveru.',
            [],
            500,
            'admin.php?r=settings&a=mail'
        );
    }

    private function pickPresetValue(array $options, string $selected, string $default): string
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

    /**
     * @param array{seo_urls:bool,post_base:string,page_base:string,tag_base:string,category_base:string} $input
     * @return array<string,array<int,string>>
     */
    private function validatePermalinkInput(array $input): array
    {
        $errors = [];
        $fields = [
            'post_base'     => 'Zadejte slug pro příspěvky.',
            'page_base'     => 'Zadejte slug pro stránky.',
            'category_base' => 'Zadejte slug pro kategorie.',
            'tag_base'      => 'Zadejte slug pro štítky.',
        ];

        foreach ($fields as $field => $emptyMessage) {
            $value = trim((string)($input[$field] ?? ''));
            if ($value === '') {
                $errors[$field][] = $emptyMessage;
                continue;
            }
            if (!preg_match('~^[a-z0-9\-]+$~', $value)) {
                $errors[$field][] = 'Použijte pouze malá písmena, čísla a pomlčky.';
            }
        }

        return $errors;
    }

    private function respondSettingsSuccess(string $message, string $redirectUrl): never
    {
        $this->respondSettings(true, 'success', $message, [], 200, $redirectUrl);
    }

    /**
     * @param array<string,array<int,string>> $errors
     */
    private function respondSettingsError(string $message, array $errors, int $status, string $redirectUrl, string $flashType = 'danger'): never
    {
        $this->respondSettings(false, $flashType, $message, $errors, $status, $redirectUrl);
    }

    /**
     * @param array<string,array<int,string>> $errors
     */
    private function respondSettings(bool $success, string $flashType, string $flashMessage, array $errors, int $status, string $redirectUrl): never
    {
        if ($this->isAjax()) {
            $payload = [
                'success' => $success,
                'flash'   => [
                    'type' => $flashType,
                    'msg'  => $flashMessage,
                ],
                'errors'  => $errors === [] ? new \stdClass() : $errors,
            ];

            $this->jsonResponse($payload, $status);
        }

        $this->redirect($redirectUrl, $flashType, $flashMessage);
    }
}
