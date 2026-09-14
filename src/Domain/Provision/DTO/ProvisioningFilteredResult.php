<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\DTO;

use Carbon\CarbonImmutable;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;

readonly class ProvisioningFilteredResult
{
    public function __construct(
        public int $resultId,
        public UuidInterface $uuid,
        public UuidInterface $tag,
        public ?UuidInterface $context,
        public string $response,
        public ProvisionStatus $status,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $requestCreatedAt,
        public ?CarbonImmutable $requestUpdatedAt,
        public string $requestData,
        public UuidInterface $requestUuid,
        public ProvisionRequestName $requestName,
        public ProvisionType $requestType,
        public ProvisionProvider $provider,
        public ?UuidInterface $retryOf = null,
        public ?UuidInterface $retryRequester = null,
    ) {
    }
}
