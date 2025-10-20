<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Settings;

use Cms\Admin\Http\AjaxResponse;
use Cms\Admin\Http\Ajax\Traits\ProvidesAjaxResponses;
use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Utils\DateTimeFactory;
use Core\Database\Init as DB;

final class MailSaveHandler
{
    use ProvidesAjaxResponses;
    use SettingsHelpers;

    public function __invoke(): AjaxResponse
    {
        $driver = $this->normalizeMailDriver((string)($_POST['mail_driver'] ?? 'php'));
        $fromEmail = isset($_POST['mail_from_email']) ? trim((string)$_POST['mail_from_email']) : '';
        if ($fromEmail !== '' && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $fromEmail = '';
        }
        $fromName = isset($_POST['mail_from_name']) ? trim((string)$_POST['mail_from_name']) : '';
        $signature = isset($_POST['mail_signature']) ? trim((string)$_POST['mail_signature']) : '';

        $smtpHost = isset($_POST['mail_smtp_host']) ? trim((string)$_POST['mail_smtp_host']) : '';
        $smtpPort = isset($_POST['mail_smtp_port']) ? (int)$_POST['mail_smtp_port'] : 587;
        if ($smtpPort <= 0 || $smtpPort > 65535) {
            $smtpPort = 587;
        }
        $smtpUsername = isset($_POST['mail_smtp_username']) ? trim((string)$_POST['mail_smtp_username']) : '';
        $smtpPassword = isset($_POST['mail_smtp_password']) ? (string)$_POST['mail_smtp_password'] : '';
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
        ])->where('id', '=', 1)->execute();

        CmsSettings::refresh();

        return $this->successResponse('E-mailové nastavení bylo uloženo.', [
            'redirect' => 'admin.php?r=settings&a=mail',
        ]);
    }
}
