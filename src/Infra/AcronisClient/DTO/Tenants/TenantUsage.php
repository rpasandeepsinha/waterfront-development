<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\DTO\Tenants;

use Waterfront\Infra\AcronisClient\DTO\Responses\OfferingItems\OfferingItem;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\MeasurementUnit;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\OfferingItemType;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\UsageName;

class TenantUsage
{
    public function __construct(
        public string $applicationId,
        public string $name,
        public UsageName $usageName,
        public OfferingItemType $type,
        public MeasurementUnit $measurementUnit,
        public string $rangeStart,
        public int $absoluteValue,
        public int $value,
        public ?string $infraId,
        public ?string $edition = null,
        public ?OfferingItem $offeringItem = null,
    ) {
    }
}
