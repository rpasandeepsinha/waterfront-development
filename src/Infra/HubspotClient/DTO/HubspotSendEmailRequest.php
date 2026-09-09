<?php

declare(strict_types=1);

namespace Waterfront\Infra\HubspotClient\DTO;

use Symfony\Component\Serializer\Attribute\SerializedPath;
use Waterfront\Infra\HubspotClient\ValueObject\EmailAddress;

class HubspotSendEmailRequest
{
    /**
     * @param EmailAddress[]|null       $cc
     * @param EmailAddress[]|null       $bcc
     * @param array<string, mixed>|null $customProperties
     */
    public function __construct(
        #[SerializedPath('[message][to]')]
        public readonly EmailAddress $to,
        #[SerializedPath('[emailId]')]
        public readonly string $emailId,
        #[SerializedPath('[message][sendId]')]
        public readonly string $sendId,
        #[SerializedPath('[message][from]')]
        public readonly ?string $from,
        #[SerializedPath('[message][cc]')]
        public readonly ?array $cc,
        #[SerializedPath('[message][bcc]')]
        public readonly ?array $bcc,
        #[SerializedPath('[customProperties]')]
        public readonly ?array $customProperties,
    ) {
    }
}
