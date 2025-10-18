<?php
declare(strict_types=1);

namespace Cms\Mail;

final class MailTemplate
{
    public function __construct(
        private readonly string $subject,
        private readonly string $htmlBody,
        private readonly ?string $textBody = null
    ) {}

    public function subject(): string
    {
        return $this->subject;
    }

    public function htmlBody(): string
    {
        return $this->htmlBody;
    }

    public function textBody(): ?string
    {
        return $this->textBody;
    }
}
