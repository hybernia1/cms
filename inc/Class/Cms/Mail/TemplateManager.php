<?php
declare(strict_types=1);

namespace Cms\Mail;

use RuntimeException;

final class TemplateManager
{
    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? dirname(__DIR__, 4) . '/resources/mail';
    }

    /**
     * @param array<string,mixed> $data
     */
    public function render(string $key, array $data = []): MailTemplate
    {
        $file = $this->basePath . '/' . $key . '.php';
        if (!is_file($file)) {
            throw new RuntimeException(sprintf('Mail template "%s" not found.', $key));
        }

        $result = $this->includeTemplate($file, $data);

        if ($result instanceof MailTemplate) {
            return $result;
        }

        if (is_array($result)) {
            return $this->fromArray($key, $result);
        }

        throw new RuntimeException(sprintf('Mail template "%s" must return MailTemplate or array.', $key));
    }

    /**
     * @param array<string,mixed> $data
     * @return mixed
     */
    private function includeTemplate(string $file, array $data)
    {
        return (static function (string $file, array $data) {
            extract($data, EXTR_SKIP);
            return require $file;
        })($file, $data);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function fromArray(string $key, array $payload): MailTemplate
    {
        if (!isset($payload['subject']) || !isset($payload['html'])) {
            throw new RuntimeException(sprintf('Mail template "%s" array must contain subject and html keys.', $key));
        }

        $subject = (string)$payload['subject'];
        $html    = (string)$payload['html'];
        $text    = array_key_exists('text', $payload) ? (string)$payload['text'] : null;

        return new MailTemplate($subject, $html, $text);
    }
}
