<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\DTO\OfferingItems;

use Symfony\Component\Serializer\Attribute\Groups;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\MeasurementUnit;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\OfferingItemStatus;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\OfferingItemType;

class OfferingItem
{
    #[Groups('put')]
    public ?string $edition = null;

    #[Groups('put')]
    public string $usageName;

    #[Groups('put')]
    public OfferingItemType $type;

    #[Groups('put')]
    public MeasurementUnit $measurementUnit;

    #[Groups('put')]
    public ?bool $locked = null;

    public ?string $updatedAt = null;

    public ?string $deletedAt = null;

    public function __construct(
        #[Groups('put')]
        public string $applicationId,
        #[Groups('put')]
        public string $name,
        #[Groups('put')]
        public string $tenantId,
        #[Groups('put')]
        public OfferingItemStatus $status,
        #[Groups('put')]
        public ?string $infraId,
        #[Groups('put')]
        public ?Quota $quota,
    ) {
    }
}
