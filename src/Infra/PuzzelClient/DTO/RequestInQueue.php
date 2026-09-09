<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient\DTO;

use DateTime;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Waterfront\Infra\PuzzelClient\Enums\MediaType;
use Waterfront\Infra\PuzzelClient\Enums\PersonalType;
use Waterfront\Infra\PuzzelClient\Enums\RequestStatus;

class RequestInQueue
{
    public function __construct(
        public ?string $requestId = null,
        public ?int $serviceId = null,
        public ?int $timeInQueue = null,
        public ?int $vip = null,
        public ?MediaType $mediaType = null,
        public ?string $requestRemoteAddress = null,
        public ?string $destination = null,
        public ?int $reservedUserId = null,
        public ?RequestStatus $requestStatus = null,
        public ?int $sla = null,
        public ?int $adjustedTimeInQueue = null,
        public ?int $ciq = null,
        public ?int $reservedUserFlag = null,
        public ?string $reservedUserName = null,
        public ?string $category = null,
        public ?string $description = null,
        public ?PersonalType $personalType = null,
        public ?int $queueId = null,
        #[SerializedName('callbackScheduledTime')]
        public ?DateTime $callbackScheduledTime = null,
    ) {
    }
}
