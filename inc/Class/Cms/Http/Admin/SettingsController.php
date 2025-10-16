<?php
declare(strict_types=1);

namespace Cms\Http\Admin;

use Cms\View\ViewEngine;
use Cms\Auth\AuthService;
use Core\Database\Init as DB;
use Cms\Settings\CmsSettings;
use Cms\Utils\AdminNavigation;
use Cms\Utils\DateTimeFactory;

final class SettingsController
{
    private ViewEngine $view;
    private AuthService $auth;

    public function __construct(string $baseViewsPath)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $this->view = new ViewEngine($baseViewsPath);
        $this->auth = new AuthService();
    }

    public function handle(string $action): void
    {
        switch ($action) {
            case 'index':
            default:
                if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->save(); return; }
                $this->index(); return;
        }
    }


    private function token(): string
    {
        if (empty($_SESSION['csrf_admin'])) $_SESSION['csrf_admin'] = bin2hex(random_bytes(16));
        return $_SESSION['csrf_admin'];
    }

    private function assertCsrf(): void
    {
        $in = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
        if (empty($_SESSION['csrf_admin']) || !hash_equals($_SESSION['csrf_admin'], (string)$in)) {
            http_response_code(419); echo 'CSRF token invalid'; exit;
        }
    }

    private function flash(string $type, string $msg): void
    {
        $_SESSION['_flash'] = ['type'=>$type,'msg'=>$msg];
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
        return $this->timezonePresets();
    }

    private function timezonePresets(): array
    {
        $data = $this->loadJson('config/timezones.json');
        $list = [];
        if (is_array($data)) {
            foreach ($data as $value) {
                if (!is_string($value)) {
                    continue;
                }
                $value = trim($value);
                if ($value === '' || in_array($value, $list, true)) {
                    continue;
                }
                $list[] = $value;
            }
        }
        return $list !== [] ? $list : \DateTimeZone::listIdentifiers();
    }

    private function normalizePresetList($values, array $fallback): array
    {
        $result = [];
        if (is_array($values)) {
            foreach ($values as $value) {
                if (!is_string($value)) {
                    continue;
                }
                $value = trim($value);
                if ($value === '' || in_array($value, $result, true)) {
                    continue;
                }
                $result[] = $value;
            }
        }
        return $result !== [] ? $result : $fallback;
    }

    private function formatPresets(): array
    {
        $data = $this->loadJson('config/date_time_formats.json');

        $dateDefaults = ['Y-m-d', 'd.m.Y', 'j. n. Y'];
        $timeDefaults = ['H:i', 'H:i:s', 'g:i A'];
        $datetimeDefaults = ['Y-m-d H:i', 'd.m.Y H:i'];

        $date = $this->normalizePresetList(is_array($data) ? ($data['date'] ?? null) : null, $dateDefaults);
        $time = $this->normalizePresetList(is_array($data) ? ($data['time'] ?? null) : null, $timeDefaults);
        $datetime = $this->normalizePresetList(is_array($data) ? ($data['datetime'] ?? null) : null, $datetimeDefaults);

        return [
            'date'     => $date,
            'time'     => $time,
            'datetime' => $datetime,
        ];
    }

    private function loadJson(string $relativePath): array
    {
        $baseDir = defined('BASE_DIR') ? BASE_DIR : dirname(__DIR__, 5);
        $path = $baseDir . '/' . ltrim($relativePath, '/');
        if (!is_file($path)) {
            return [];
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            return [];
        }
        $decoded = json_decode($contents, true);
        return is_array($decoded) ? $decoded : [];
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

        $this->view->render('settings/index', [
            'pageTitle'   => 'Nastavení',
            'nav'         => AdminNavigation::build('settings:general'),
            'currentUser' => $this->auth->user(),
            'flash'       => $_SESSION['_flash'] ?? null,
            'settings'    => $settings,
            'csrf'        => $this->token(),
            'timezones'   => $this->timezones(),
            'previewNow'  => $now->format($dateFmt.' '.$timeFmt),
            'formatPresets' => $this->formatPresets(),
        ]);
        unset($_SESSION['_flash']);
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
        header('Location: admin.php?r=settings');
        exit;
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
