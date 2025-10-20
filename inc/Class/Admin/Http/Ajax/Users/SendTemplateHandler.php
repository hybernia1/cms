<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Users;

use Cms\Admin\Http\AjaxResponse;
use Cms\Admin\Http\Ajax\Traits\ProvidesAjaxResponses;
use Cms\Admin\Mail\MailService;
use Cms\Admin\Mail\TemplateManager;
use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Utils\DateTimeFactory;
use Cms\Admin\Utils\LinkGenerator;
use Core\Database\Init as DB;
use Throwable;

final class SendTemplateHandler
{
    use ProvidesAjaxResponses;

    public function __invoke(): AjaxResponse
    {
        $userId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $templateKey = isset($_POST['template']) ? trim((string)$_POST['template']) : '';

        if ($userId <= 0 || $templateKey === '') {
            return $this->errorResponse('Vyberte uživatele a šablonu.', 422);
        }

        $user = DB::query()->table('users')->select(['*'])->where('id', '=', $userId)->first();
        if (!$user) {
            return $this->errorResponse('Uživatel nenalezen.', 404);
        }

        $email = (string)($user['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->errorResponse('Uživatel nemá platný e-mail.', 422);
        }

        $settings = new CmsSettings();
        $templateManager = new TemplateManager();

        $templateData = [
            'siteTitle' => $settings->siteTitle(),
            'userName'  => (string)($user['name'] ?? ''),
            'userEmail' => $email,
            'user'      => $user,
        ];

        if ($templateKey === 'lost_password') {
            $links = new LinkGenerator(null, $settings);
            try {
                $token = bin2hex(random_bytes(20));
            } catch (Throwable $exception) {
                $msg = $exception->getMessage() ?: 'Nepodařilo se vygenerovat resetovací odkaz.';

                return $this->errorResponse($msg, 500);
            }

            $expiresAt = DateTimeFactory::now()->modify('+1 hour')->format('Y-m-d H:i:s');

            try {
                DB::query()->table('users')->update([
                    'token'        => $token,
                    'token_expire' => $expiresAt,
                    'updated_at'   => DateTimeFactory::nowString(),
                ])->where('id', '=', $userId)->execute();
            } catch (Throwable $exception) {
                $msg = $exception->getMessage() ?: 'Nepodařilo se uložit resetovací token.';

                return $this->errorResponse($msg, 500);
            }

            $user['token'] = $token;
            $user['token_expire'] = $expiresAt;
            $templateData['user'] = $user;
            $templateData['resetUrl'] = $links->absolute($links->reset($token, $userId));
        }

        try {
            $template = $templateManager->render($templateKey, $templateData);
        } catch (Throwable $exception) {
            $msg = $exception->getMessage() ?: 'Šablonu se nepodařilo načíst.';

            return $this->errorResponse($msg, 500);
        }

        $mailService = new MailService($settings);
        $ok = false;
        try {
            $ok = $mailService->sendTemplate($email, $template, (string)($user['name'] ?? '') ?: null);
        } catch (Throwable $exception) {
            $msg = $exception->getMessage() ?: 'E-mail se nepodařilo odeslat.';

            return $this->errorResponse($msg, 500);
        }

        if (!$ok) {
            return $this->errorResponse('E-mail se nepodařilo odeslat.', 500);
        }

        return $this->successResponse('E-mail byl odeslán.', [
            'redirect' => 'admin.php?r=users&a=edit&id=' . $userId,
        ]);
    }
}
