<?php
declare(strict_types=1);

namespace Cms\Admin\Mail;

use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Utils\LinkGenerator;

final class NewsletterMailer
{
    public function __construct(
        private readonly MailService $mailService,
        private readonly TemplateManager $templates,
        private readonly LinkGenerator $links,
        private readonly CmsSettings $settings
    ) {
    }

    /**
     * @param array<string,mixed> $subscriber
     */
    public function sendConfirmationEmail(array $subscriber): bool
    {
        $email = trim((string)($subscriber['email'] ?? ''));
        $confirmToken = trim((string)($subscriber['confirm_token'] ?? ''));
        if ($email === '' || $confirmToken === '') {
            return false;
        }

        $unsubscribeToken = trim((string)($subscriber['unsubscribe_token'] ?? ''));

        $confirmUrl = $this->links->absolute(
            $this->links->newsletterConfirm($confirmToken)
        );
        $unsubscribeUrl = $unsubscribeToken !== ''
            ? $this->links->absolute($this->links->newsletterUnsubscribe($unsubscribeToken))
            : null;

        $data = [
            'siteTitle' => $this->settings->siteTitle(),
            'subscriberEmail' => $email,
            'confirmUrl' => $confirmUrl,
            'unsubscribeUrl' => $unsubscribeUrl,
        ];

        $template = $this->templates->render('newsletter_confirm', $data);

        return $this->mailService->sendTemplate($email, $template);
    }

    /**
     * @param array<string,mixed> $subscriber
     */
    public function sendUnsubscribeEmail(array $subscriber): bool
    {
        $email = trim((string)($subscriber['email'] ?? ''));
        if ($email === '') {
            return false;
        }

        $data = [
            'siteTitle' => $this->settings->siteTitle(),
            'subscriberEmail' => $email,
            'subscribeUrl' => $this->links->absolute($this->links->home()),
        ];

        $template = $this->templates->render('newsletter_unsubscribe', $data);

        return $this->mailService->sendTemplate($email, $template);
    }
}
