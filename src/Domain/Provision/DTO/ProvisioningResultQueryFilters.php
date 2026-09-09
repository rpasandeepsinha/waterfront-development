<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\DTO;

use Carbon\CarbonImmutable;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;

readonly class ProvisioningResultQueryFilters
{
    /**
     * @param list<ProvisionStatus> $provisionStatus
     */
    public function __construct(
        public ?UuidInterface $uuid = null,
        public ?UuidInterface $requestUuid = null,
        public array $provisionStatus = [],
        public ?CarbonImmutable $fromDate = null,
        public ?CarbonImmutable $toDate = null,
        public ?UuidInterface $tag = null,
        public ?ProvisionType $requestType = null,
        public ?UuidInterface $retryOf = null,
        public ?UuidInterface $retryRequester = null,
        public bool $onlyRetryRequests = false,
        public bool $onlyCreateRequests = false,
    ) {
    }
}
