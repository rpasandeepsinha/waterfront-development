<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Dto;

use Carbon\CarbonImmutable;

class Microsoft365DeploymentDTO
{
    public function __construct(
        public int $id,
        public string $subscriptionUuid,
        public string $productName,
        public string $productSlug,
        public string $administrativeStatus,
        public ?string $technicalStatus,
        public CarbonImmutable $startDate,
        public CarbonImmutable $endDate,
        public int $period,
        public int $contractPeriod,
        public int $billingPeriod,
        public int $seatCount,
        public int $canceledSeatCount,
        public NextInvoiceDTO $nextInvoice,
    ) {
    }
}
