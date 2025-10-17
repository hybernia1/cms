<?php
declare(strict_types=1);

namespace Core\Mail;

final class Mailer
{
    public function __construct(
        private readonly ?array $smtp = null, // ['host'=>'','port'=>587,'username'=>'','password'=>'','secure'=>'tls','from'=>['email'=>'','name'=>'']]
        private readonly ?array $from = null  // ['email'=>'','name'=>''] – když nechceš SMTP
    ) {}

    /**
     * Odeslání e-mailu. Pokud je dostupný PHPMailer (composer), použije se.
     * Jinak fallback na mail().
     */
    public function send(string $toEmail, string $subject, string $htmlBody, ?string $toName = null, ?string $textAlt = null): bool
    {
        if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            return $this->sendViaPHPMailer($toEmail, $subject, $htmlBody, $toName, $textAlt);
        }

        if ($this->smtp) {
            $sent = $this->sendViaSmtp($toEmail, $subject, $htmlBody, $toName, $textAlt);
            if ($sent !== null) {
                return $sent;
            }
        }

        return $this->sendViaMail($toEmail, $subject, $htmlBody, $toName, $textAlt);
    }

    private function sendViaPHPMailer(string $toEmail, string $subject, string $htmlBody, ?string $toName, ?string $textAlt): bool
    {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            if ($this->smtp) {
                $mail->isSMTP();
                $mail->Host       = (string)$this->smtp['host'];
                $mail->Port       = (int)($this->smtp['port'] ?? 587);
                $mail->SMTPAuth   = true;
                $mail->Username   = (string)$this->smtp['username'];
                $mail->Password   = (string)$this->smtp['password'];
                $secure           = (string)($this->smtp['secure'] ?? 'tls');
                if ($secure) $mail->SMTPSecure = $secure;
                $from = $this->smtp['from'] ?? null;
            } else {
                $from = $this->from ?? null;
            }

            if ($from) {
                $mail->setFrom((string)$from['email'], (string)($from['name'] ?? ''));
            }

            $mail->addAddress($toEmail, (string)($toName ?? ''));
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textAlt ?? strip_tags($htmlBody);

            return $mail->send();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function sendViaMail(string $toEmail, string $subject, string $htmlBody, ?string $toName, ?string $textAlt): bool
    {
        $fromEmail = (string)($this->from['email'] ?? ini_get('sendmail_from') ?: 'no-reply@localhost');
        $fromName  = (string)($this->from['name'] ?? 'Website');

        $to = $toName ? sprintf('"%s" <%s>', $this->q($toName), $toEmail) : $toEmail;

        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . sprintf('"%s" <%s>', $this->q($fromName), $fromEmail);
        $headers[] = 'Reply-To: ' . $fromEmail;
        $headers[] = 'X-Mailer: PHP/' . phpversion();

        return @mail($to, '=?UTF-8?B?'.base64_encode($subject).'?=', $htmlBody, implode("\r\n",$headers));
    }

    private function sendViaSmtp(string $toEmail, string $subject, string $htmlBody, ?string $toName, ?string $textAlt): ?bool
    {
        if (!$this->smtp) {
            return null;
        }

        $fromConfig = $this->smtp['from'] ?? $this->from ?? [
            'email' => ini_get('sendmail_from') ?: 'no-reply@localhost',
            'name'  => 'Website',
        ];

        $host = trim((string)($this->smtp['host'] ?? ''));
        if ($host === '') {
            return null;
        }

        $client = new SmtpClient(
            $host,
            (int)($this->smtp['port'] ?? 587),
            (string)($this->smtp['secure'] ?? ''),
            (string)($this->smtp['username'] ?? ''),
            (string)($this->smtp['password'] ?? '')
        );

        $from = [
            'email' => (string)($fromConfig['email'] ?? ''),
            'name'  => (string)($fromConfig['name'] ?? ''),
        ];

        $to = [
            'email' => $toEmail,
            'name'  => (string)($toName ?? ''),
        ];

        $textBody = $textAlt ?? strip_tags($htmlBody);

        $result = $client->send($from, $to, $subject, $htmlBody, $textBody);

        return $result;
    }

    private function q(string $s): string
    {
        return addcslashes($s, '"\\');
    }
}
