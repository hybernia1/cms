<?php
declare(strict_types=1);

namespace Cms\Http\Admin;

use Cms\View\ViewEngine;
use Cms\Auth\AuthService;
use Core\Database\Init as DB;
use Cms\Settings\CmsSettings;

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

    private function nav(): array
    {
        return [
            ['key'=>'dashboard','label'=>'Dashboard','href'=>'admin.php?r=dashboard','active'=>false],
            ['key'=>'posts:post','label'=>'Příspěvky','href'=>'admin.php?r=posts&type=post','active'=>false],
            ['key'=>'media','label'=>'Média','href'=>'admin.php?r=media','active'=>false],
            ['key'=>'terms','label'=>'Termy','href'=>'admin.php?r=terms','active'=>false],
            ['key'=>'comments','label'=>'Komentáře','href'=>'admin.php?r=comments','active'=>false],
            ['key'=>'users','label'=>'Uživatelé','href'=>'admin.php?r=users','active'=>false],
            ['key'=>'themes','label'=>'Šablony','href'=>'admin.php?r=themes','active'=>false],
            ['key'=>'navigation','label'=>'Navigace','href'=>'admin.php?r=navigation','active'=>false],
            ['key'=>'settings','label'=>'Nastavení','href'=>'admin.php?r=settings','active'=>true],
            ['key'=>'migrations','label'=>'Migrace','href'=>'admin.php?r=migrations','active'=>false],
        ];
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
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s')
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
        return $row ?? [];
    }

    private function timezones(): array
    {
        return \DateTimeZone::listIdentifiers();
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
            'nav'         => $this->nav(),
            'currentUser' => $this->auth->user(),
            'flash'       => $_SESSION['_flash'] ?? null,
            'settings'    => $settings,
            'csrf'        => $this->token(),
            'timezones'   => $this->timezones(),
            'previewNow'  => $now->format($dateFmt.' '.$timeFmt),
        ]);
        unset($_SESSION['_flash']);
    }

    private function sanitizeFormat(string $fmt, string $default): string
    {
        $fmt = trim($fmt);
        if ($fmt === '') return $default;
        // Povolené znaky: písmena formátovacích tokenů PHP date() + separátory
        // (nepovolujeme backticks a control chars)
        if (!preg_match('~^[A-Za-z\-\._:/,\s\\\|]+$~', $fmt)) {
            return $default;
        }
        if (strlen($fmt) > 64) $fmt = substr($fmt, 0, 64);
        return $fmt;
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

        $dateFormat = $this->sanitizeFormat((string)($_POST['date_format'] ?? ''), 'Y-m-d');
        $timeFormat = $this->sanitizeFormat((string)($_POST['time_format'] ?? ''), 'H:i');

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

        DB::query()->table('settings')->update([
            'site_title'         => $title !== '' ? $title : 'Moje stránka',
            'site_email'         => $email,
            'date_format'        => $dateFormat,
            'time_format'        => $timeFormat,
            'timezone'           => $tz,
            'allow_registration' => $allowReg,
            'site_url'           => $siteUrl,
            'updated_at'         => date('Y-m-d H:i:s'),
        ])->where('id','=',1)->execute();

        // promaž cache settings
        CmsSettings::refresh();

        $this->flash('success','Nastavení uloženo.');
        header('Location: admin.php?r=settings');
        exit;
    }
}
