<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\DTO;

use Carbon\CarbonImmutable;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionType;

readonly class ProvisioningRequestDTO
{
    /**
     * @param ProvisionResultDTO[] $provisioningResults
     */
    public function __construct(
        public UuidInterface $uuid,
        public UuidInterface $tag,
        public string $requestData,
        public ProvisionType $requestType,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $updatedAt,
        public ProvisionRequestName $requestName,
        public ?UuidInterface $context,
        public ProvisionProvider $provisionProvider,
        public ?UuidInterface $retryOf,
        public ?UuidInterface $retryRequester,
        public array $provisioningResults,
    ) {
    }
}
