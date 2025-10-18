<?php
declare(strict_types=1);

namespace Cms\Mail;

use Cms\Settings\CmsSettings;
use Core\Mail\Mailer;

final class MailService
{
    public function __construct(
        private readonly CmsSettings $settings = new CmsSettings()
    ) {}

    public function send(string $toEmail, string $subject, string $htmlBody, ?string $toName = null, ?string $textAlt = null): bool
    {
        $signature = $this->signature();
        $htmlWithSignature = $this->appendSignatureToHtml($htmlBody, $signature);
        $textWithSignature = $this->buildTextBody($htmlBody, $textAlt, $signature);

        return $this->buildMailer()->send($toEmail, $subject, $htmlWithSignature, $toName, $textWithSignature);
    }

    public function sendTemplate(string $toEmail, MailTemplate $template, ?string $toName = null): bool
    {
        return $this->send(
            $toEmail,
            $template->subject(),
            $template->htmlBody(),
            $toName,
            $template->textBody()
        );
    }

    private function buildMailer(): Mailer
    {
        $fromEmail = $this->settings->mailFromEmail() ?: ($this->settings->siteEmail() ?: 'no-reply@localhost');
        $fromName  = $this->settings->mailFromName();
        $from = [
            'email' => $fromEmail,
            'name'  => $fromName !== '' ? $fromName : $this->settings->siteTitle(),
        ];

        if ($this->settings->mailDriver() === 'smtp') {
            $smtp = $this->settings->mailSmtp();
            if ($smtp['host'] !== '' && $smtp['username'] !== '' && $smtp['password'] !== '') {
                return new Mailer([
                    'host'     => $smtp['host'],
                    'port'     => $smtp['port'],
                    'username' => $smtp['username'],
                    'password' => $smtp['password'],
                    'secure'   => $smtp['secure'],
                    'from'     => $from,
                ]);
            }
        }

        return new Mailer(null, $from);
    }

    private function appendSignatureToHtml(string $html, string $signature): string
    {
        $signature = trim($signature);
        if ($signature === '') {
            return $html;
        }
        $escaped = nl2br(htmlspecialchars($signature, ENT_QUOTES, 'UTF-8'), false);
        return rtrim($html) . '<br><br>' . $escaped;
    }

    private function buildTextBody(string $htmlBody, ?string $textAlt, string $signature): string
    {
        $text = $textAlt ?? $this->plainTextFromHtml($htmlBody);
        $text = trim($text);
        if (trim($signature) !== '') {
            $text .= "\n\n" . trim($signature);
        }
        return $text;
    }

    private function plainTextFromHtml(string $html): string
    {
        $normalized = preg_replace('/<\/(p|div)>/i', "\n", $html);
        $normalized = preg_replace('/<br\s*\/?\s*>/i', "\n", $normalized ?? $html);
        $text = strip_tags($normalized ?? $html);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        return preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
    }

    private function signature(): string
    {
        return trim($this->settings->mailSignature());
    }
}
