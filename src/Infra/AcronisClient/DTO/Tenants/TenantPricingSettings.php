<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\DTO\Tenants;

use Symfony\Component\Serializer\Attribute\Groups;
use Waterfront\Infra\AcronisClient\Enums\Tenants\PricingCurrency;
use Waterfront\Infra\AcronisClient\Enums\Tenants\PricingMode;

class TenantPricingSettings
{
    public ?string $productionStartDate = null;

    public function __construct(
        #[Groups(['put'])]
        public ?int $version,
        #[Groups(['put'])]
        public ?PricingMode $mode,
        #[Groups(['put'])]
        public ?PricingCurrency $currency,
    ) {
    }
}
