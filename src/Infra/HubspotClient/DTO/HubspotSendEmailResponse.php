<?php

declare(strict_types=1);

namespace Waterfront\Infra\HubspotClient\DTO;

use DateTimeInterface;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Attribute\SerializedPath;
use Waterfront\Infra\HubspotClient\Enum\EmailSendResult;
use Waterfront\Infra\HubspotClient\Enum\EmailSendStatus;

class HubspotSendEmailResponse
{
    public function __construct(
        #[SerializedPath('[eventId][id]')]
        public readonly ?UuidInterface $eventId,
        #[SerializedPath('[eventId][created]')]
        public readonly ?DateTimeInterface $createdAt,
        #[SerializedPath('[statusId]')]
        public readonly string $statusId,
        #[SerializedPath('[status]')]
        public readonly EmailSendStatus $status,
        #[SerializedPath('[sendResult]')]
        public readonly ?EmailSendResult $sendResult,
        #[SerializedPath('[requestedAt]')]
        public readonly DateTimeInterface $requestedAt,
        #[SerializedPath('[startedAt]')]
        public readonly ?DateTimeInterface $startedAt,
        #[SerializedPath('[completedAt]')]
        public readonly ?DateTimeInterface $completedAt,
    ) {
    }
}
