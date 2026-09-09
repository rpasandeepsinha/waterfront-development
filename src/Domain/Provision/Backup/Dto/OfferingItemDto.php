<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Dto;

use Waterfront\Domain\Provision\Backup\Acronis\Enums\OfferingItemPropertyName;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\OfferingItemStatus;

class OfferingItemDto
{
    public function __construct(
        public OfferingItemPropertyName $propertyName,
        public ?int $quota,
        public OfferingItemStatus $status,
    ) {
    }
}
