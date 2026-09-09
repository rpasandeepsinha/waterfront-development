<?php

declare(strict_types=1);

namespace Waterfront\Domain\Mailer\Dto;

class MailTemplate
{
    public function __construct(
        public readonly string $subject,
        public readonly string $header,
        public readonly string $body,
        public readonly ?string $footer,
        public readonly string $logoPath,
        public readonly ?string $greeting,
        public readonly string $class,
        public readonly string $slug,
    ) {
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'subject' => $this->subject,
            'header' => $this->header,
            'body' => $this->body,
            'footer' => $this->footer,
            'logoPath' => $this->logoPath,
            'greeting' => $this->greeting,
        ];
    }
}
