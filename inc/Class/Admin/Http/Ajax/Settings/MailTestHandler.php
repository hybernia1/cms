<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Settings;

use Cms\Admin\Http\AjaxResponse;
use Cms\Admin\Http\Ajax\Traits\ProvidesAjaxResponses;
use Cms\Admin\Mail\MailService;
use Cms\Admin\Mail\TemplateManager;
use Cms\Admin\Settings\CmsSettings;

final class MailTestHandler
{
    use ProvidesAjaxResponses;

    public function __invoke(): AjaxResponse
    {
        $email = isset($_POST['test_email']) ? trim((string)$_POST['test_email']) : '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->errorResponse('Zadejte platnou e-mailovou adresu pro test.', 422, null, 'danger', [
                'redirect' => 'admin.php?r=settings&a=mail',
            ]);
        }

        $settings = new CmsSettings();
        $mailService = new MailService($settings);
        $template = (new TemplateManager())->render('test_message', [
            'siteTitle' => $settings->siteTitle(),
        ]);

        $ok = $mailService->sendTemplate($email, $template);
        if ($ok) {
            $message = sprintf('Testovací e-mail byl odeslán na %s.', $email);

            return $this->successResponse($message, [
                'redirect' => 'admin.php?r=settings&a=mail',
            ]);
        }

        return $this->errorResponse('Testovací e-mail se nepodařilo odeslat. Zkontrolujte nastavení serveru.', 500, null, 'danger', [
            'redirect' => 'admin.php?r=settings&a=mail',
        ]);
    }
}
