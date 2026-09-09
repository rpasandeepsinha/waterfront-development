<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\DTO;

use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;

readonly class SubscriptionUpdateRequestDTO
{
    /**
     * @param int<0,max> $gross_price
     * @param int<0,max> $net_price
     */
    public function __construct(
        public Product $product,
        public string $domain,
        public AdministrativeStatus $administrative_status,
        public TechnicalStatus $technical_status,
        public int $gross_price,
        public int $net_price,
    ) {
    }
}
