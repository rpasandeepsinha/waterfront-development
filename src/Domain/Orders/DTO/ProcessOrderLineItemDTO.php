<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\DTO;

use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;

readonly class ProcessOrderLineItemDTO
{
    public function __construct(
        public bool $manageSubscriptions,
        public AdministrativeStatus $administrativeStatus,
        public TechnicalStatus $technicalStatus,
        public ?int $parentSubscriptionId,
    ) {
    }
}
