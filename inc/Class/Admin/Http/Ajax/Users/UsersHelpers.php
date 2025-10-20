<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Users;

use Cms\Admin\Mail\MailService;
use Cms\Admin\Mail\TemplateManager;
use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Utils\LinkGenerator;

trait UsersHelpers
{
    protected function listUrl(string $q, int $page): string
    {
        $query = ['r' => 'users'];
        if ($q !== '') {
            $query['q'] = $q;
        }
        if ($page > 1) {
            $query['page'] = $page;
        }

        $qs = http_build_query($query);

        return $qs === '' ? 'admin.php?r=users' : 'admin.php?' . $qs;
    }

    /**
     * @param array<string,mixed> $user
     * @param array<string,mixed> $extra
     */
    protected function notifyUser(array $user, string $templateKey, array $extra = []): void
    {
        $email = (string)($user['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $settings = new CmsSettings();
        $data = array_merge([
            'siteTitle' => $settings->siteTitle(),
            'userName'  => (string)($user['name'] ?? ''),
            'userEmail' => $email,
            'loginUrl'  => $this->loginUrl($settings),
        ], $extra);

        $manager = new TemplateManager();

        try {
            $template = $manager->render($templateKey, $data);
        } catch (\Throwable) {
            return;
        }

        try {
            (new MailService($settings))->sendTemplate($email, $template, (string)($user['name'] ?? '') ?: null);
        } catch (\Throwable) {
            // ignore mailing errors
        }
    }

    protected function loginUrl(CmsSettings $settings): string
    {
        $links = new LinkGenerator(null, $settings);
        return $links->absolute($links->login());
    }
}
