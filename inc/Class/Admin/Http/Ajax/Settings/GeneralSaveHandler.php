<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Settings;

use Cms\Admin\Http\AjaxResponse;
use Cms\Admin\Http\Ajax\Traits\ProvidesAjaxResponses;
use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Utils\DateTimeFactory;
use Cms\Admin\Utils\SettingsPresets;
use Core\Database\Init as DB;

final class GeneralSaveHandler
{
    use ProvidesAjaxResponses;
    use SettingsHelpers;

    public function __invoke(): AjaxResponse
    {
        $currentSettings = $this->loadSettings();

        $title = isset($_POST['site_title']) ? trim((string)$_POST['site_title']) : '';
        $email = isset($_POST['site_email']) ? trim((string)$_POST['site_email']) : '';

        $formatPresets = $this->formatPresets();
        $dateOptions = is_array($formatPresets['date'] ?? null) ? $formatPresets['date'] : [];
        $timeOptions = is_array($formatPresets['time'] ?? null) ? $formatPresets['time'] : [];

        $dateFormat = $this->pickPresetValue($dateOptions, (string)($_POST['date_format'] ?? ''), 'Y-m-d');
        $timeFormat = $this->pickPresetValue($timeOptions, (string)($_POST['time_format'] ?? ''), 'H:i');

        $tzInput = isset($_POST['timezone']) ? (string)$_POST['timezone'] : 'UTC+01:00';
        $tz = SettingsPresets::normalizeTimezone($tzInput);
        $tzList = $this->timezones();
        if (!in_array($tz, $tzList, true)) {
            $tz = 'UTC+00:00';
        }

        $allowReg = isset($_POST['allow_registration']) && (int)$_POST['allow_registration'] === 1 ? 1 : 0;
        $autoApproveInput = (int)($_POST['registration_auto_approve'] ?? (int)($currentSettings['registration_auto_approve'] ?? 1));
        $autoApprove = $autoApproveInput === 1 ? 1 : 0;
        if ($allowReg !== 1) {
            $autoApprove = isset($currentSettings['registration_auto_approve'])
                ? (int)$currentSettings['registration_auto_approve']
                : 1;
        }

        $siteUrlIn = isset($_POST['site_url']) ? trim((string)$_POST['site_url']) : '';
        if ($siteUrlIn === '') {
            $siteUrl = $this->detectSiteUrl();
        } else {
            $siteUrl = $this->normalizeUrl($siteUrlIn);
            if (filter_var($siteUrl, FILTER_VALIDATE_URL) === false) {
                $siteUrl = $this->detectSiteUrl();
            }
        }

        $webpEnabled = isset($_POST['webp_enabled']) && (int)$_POST['webp_enabled'] === 1;
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
            'registration_auto_approve' => $autoApprove,
            'site_url'           => $siteUrl,
            'data'               => $dataJson,
            'updated_at'         => DateTimeFactory::nowString(),
        ])->where('id', '=', 1)->execute();

        CmsSettings::refresh();

        return $this->successResponse('Nastavení uloženo.', [
            'redirect' => 'admin.php?r=settings',
        ]);
    }
}
