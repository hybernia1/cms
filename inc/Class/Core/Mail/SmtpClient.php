<?php
declare(strict_types=1);

namespace Core\Mail;

use RuntimeException;

final class SmtpClient
{
    private const DEFAULT_TIMEOUT = 30;

    /** @var resource|null */
    private $socket = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port = 587,
        private readonly string $secure = '',
        private readonly string $username = '',
        private readonly string $password = '',
        private readonly int $timeout = self::DEFAULT_TIMEOUT
    ) {
    }

    public function send(
        array $from,
        array $to,
        string $subject,
        string $htmlBody,
        string $textBody = ''
    ): bool {
        try {
            $this->openSocket();
            $this->expect([220]);

            $hostname = $this->determineHelloHostname();
            $this->command('EHLO ' . $hostname, [250]);

            if ($this->isStartTls()) {
                $this->command('STARTTLS', [220]);
                $this->enableCrypto();
                $this->command('EHLO ' . $hostname, [250]);
            }

            if ($this->shouldAuthenticate()) {
                $this->command('AUTH LOGIN', [334]);
                $this->command(base64_encode($this->username), [334]);
                $this->command(base64_encode($this->password), [235]);
            }

            $fromEmail = (string)($from['email'] ?? '');
            if ($fromEmail === '') {
                throw new RuntimeException('Missing from e-mail.');
            }

            $toEmail = (string)($to['email'] ?? '');
            if ($toEmail === '') {
                throw new RuntimeException('Missing recipient e-mail.');
            }

            $this->command('MAIL FROM:<'.$this->escapeAddress($fromEmail).'>', [250]);
            $this->command('RCPT TO:<'.$this->escapeAddress($toEmail).'>', [250, 251]);
            $this->command('DATA', [354]);

            $message = $this->buildMessage($from, $to, $subject, $htmlBody, $textBody);
            $this->write($message . "\r\n.");
            $this->expect([250]);

            $this->command('QUIT', [221]);
            $this->close();
            return true;
        } catch (RuntimeException $e) {
            $this->close();
            return false;
        }
    }

    private function openSocket(): void
    {
        if ($this->socket) {
            return;
        }

        $secure = strtolower($this->secure);
        $target = $this->host;
        if ($secure === 'ssl') {
            $target = 'ssl://' . $target;
        }

        $contextOptions = [];
        if ($secure === 'ssl' || $secure === 'tls') {
            $contextOptions['ssl'] = [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ];
        }

        $context = stream_context_create($contextOptions);

        $socket = @stream_socket_client(
            $target . ':' . $this->port,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!is_resource($socket)) {
            throw new RuntimeException(sprintf('Unable to connect to SMTP server: %s (%d)', $errstr, $errno));
        }

        stream_set_timeout($socket, $this->timeout);
        $this->socket = $socket;
    }

    private function enableCrypto(): void
    {
        if (!$this->socket) {
            throw new RuntimeException('No open socket.');
        }

        $cryptoMethod = $this->resolveCryptoMethod();

        if (!@stream_socket_enable_crypto($this->socket, true, $cryptoMethod)) {
            throw new RuntimeException('Unable to establish TLS connection.');
        }
    }

    private function resolveCryptoMethod(): int
    {
        $methods = [];
        foreach ([
            'STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT',
            'STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT',
            'STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT',
            'STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT',
            'STREAM_CRYPTO_METHOD_TLS_CLIENT',
            'STREAM_CRYPTO_METHOD_SSLv23_CLIENT',
        ] as $constant) {
            if (defined($constant)) {
                $methods[] = constant($constant);
            }
        }

        if ($methods === []) {
            return STREAM_CRYPTO_METHOD_TLS_CLIENT;
        }

        $method = array_shift($methods);
        foreach ($methods as $candidate) {
            $method |= $candidate;
        }

        return $method;
    }

    private function shouldAuthenticate(): bool
    {
        return $this->username !== '' && $this->password !== '';
    }

    private function isStartTls(): bool
    {
        return strtolower($this->secure) === 'tls';
    }

    private function determineHelloHostname(): string
    {
        $host = gethostname();
        if (is_string($host) && $host !== '') {
            return $host;
        }
        return 'localhost';
    }

    private function buildMessage(array $from, array $to, string $subject, string $htmlBody, string $textBody): string
    {
        $fromEmail = (string)($from['email'] ?? 'no-reply@localhost');
        $fromName  = (string)($from['name'] ?? '');
        $toEmail   = (string)($to['email'] ?? '');
        $toName    = (string)($to['name'] ?? '');

        $subjectHeader = $this->encodeHeader((string)$subject);
        $fromHeader    = $this->formatAddress($fromEmail, $fromName);
        $toHeader      = $this->formatAddress($toEmail, $toName);

        $headers = [];
        $headers[] = 'Date: ' . $this->formatDate();
        $headers[] = 'From: ' . $fromHeader;
        $headers[] = 'Reply-To: ' . $fromHeader;
        $headers[] = 'To: ' . $toHeader;
        $headers[] = 'Subject: ' . $subjectHeader;
        $headers[] = 'Message-ID: ' . $this->generateMessageId($fromEmail);
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'X-Mailer: PHP/' . PHP_VERSION;

        $normalizedHtml = $this->normalizeLineEndings($htmlBody);
        $normalizedText = $this->normalizeLineEndings($textBody);

        if (trim($normalizedText) !== '') {
            $boundary = $this->generateBoundary();
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

            $parts = [];
            $parts[] = '--' . $boundary;
            $parts[] = 'Content-Type: text/plain; charset=UTF-8';
            $parts[] = 'Content-Transfer-Encoding: 8bit';
            $parts[] = '';
            $parts[] = $normalizedText;
            $parts[] = '';
            $parts[] = '--' . $boundary;
            $parts[] = 'Content-Type: text/html; charset=UTF-8';
            $parts[] = 'Content-Transfer-Encoding: 8bit';
            $parts[] = '';
            $parts[] = $normalizedHtml;
            $parts[] = '';
            $parts[] = '--' . $boundary . '--';

            $body = implode("\r\n", $parts);
        } else {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: 8bit';
            $body = $normalizedHtml;
        }

        $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;
        return $this->dotStuff($message);
    }

    private function normalizeLineEndings(string $text): string
    {
        if ($text === '') {
            return '';
        }
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        return str_replace("\n", "\r\n", $text);
    }

    private function dotStuff(string $message): string
    {
        $message = preg_replace('/\r\n\./', "\r\n..", $message) ?? $message;
        if (str_starts_with($message, '.')) {
            $message = '.' . $message;
        }
        return $message;
    }

    private function formatAddress(string $email, string $name): string
    {
        $email = trim($email);
        $name  = trim($name);
        if ($name === '') {
            return $email;
        }
        return sprintf('"%s" <%s>', $this->encodeHeader($name), $email);
    }

    private function encodeHeader(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (function_exists('mb_encode_mimeheader')) {
            $encoded = mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
            if ($encoded !== false) {
                return $encoded;
            }
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function formatDate(): string
    {
        return gmdate('D, d M Y H:i:s \G\M\T');
    }

    private function generateBoundary(): string
    {
        return 'b' . bin2hex(random_bytes(16));
    }

    private function generateMessageId(string $fromEmail): string
    {
        $domain = 'localhost';
        if (str_contains($fromEmail, '@')) {
            $domain = substr($fromEmail, strpos($fromEmail, '@') + 1) ?: $domain;
        }
        return sprintf('<%s@%s>', bin2hex(random_bytes(16)), $domain);
    }

    private function escapeAddress(string $email): string
    {
        return preg_replace('/[^A-Za-z0-9@._+-]/', '', $email) ?? $email;
    }

    private function write(string $data): void
    {
        if (!$this->socket) {
            throw new RuntimeException('No open socket.');
        }

        $result = @fwrite($this->socket, $data . "\r\n");
        if ($result === false) {
            throw new RuntimeException('Failed to write to socket.');
        }
    }

    private function command(string $command, array $expectCodes): void
    {
        $this->write($command);
        $this->expect($expectCodes);
    }

    private function expect(array $expectCodes): void
    {
        if (!$this->socket) {
            throw new RuntimeException('No open socket.');
        }

        $response = '';
        $code = 0;
        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                $code = (int)substr($line, 0, 3);
                break;
            }
        }

        if ($response === '') {
            throw new RuntimeException('Empty response from SMTP server.');
        }

        if (!in_array($code, $expectCodes, true)) {
            throw new RuntimeException(sprintf('Unexpected SMTP server response: %s', trim($response)));
        }
    }

    private function close(): void
    {
        if ($this->socket && is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
    }
}
