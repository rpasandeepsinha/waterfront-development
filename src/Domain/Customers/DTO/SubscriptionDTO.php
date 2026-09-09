<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\DTO;

use Carbon\CarbonImmutable;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;

readonly class SubscriptionDTO
{
    public function __construct(
        public CreateSubscriptionDTO $createSubscription,
        public Product $product,
        public Price $productPrice
    ) {
    }

    /**
     * @return array<mixed>
     */
    public function toAdministrativeArray(): array
    {
        return [
            'product_uuid' => $this->product->uuid,
            'billing_period' => $this->createSubscription->billingPeriod,
            'contract_period' => $this->createSubscription->contractPeriod,
            'start_date' => new CarbonImmutable($this->createSubscription->startDate),
            'next_billing_date' => new CarbonImmutable($this->createSubscription->nextBillingDate),
            'end_date' => new CarbonImmutable($this->createSubscription->nextContractDate),
            'cancel_date' => $this->createSubscription->cancelDate !== null ?
                new CarbonImmutable($this->createSubscription->cancelDate) : null,
            'internal_comment' => $this->createSubscription->internalComment,
            'domain' => $this->createSubscription->domain,
            'administrative_status' => $this->createSubscription->cancelDate === null ?
                AdministrativeStatus::ACTIVE->value :
                AdministrativeStatus::CANCELED->value,
            'net_price' => $this->productPrice->calculatedPrice,
            'gross_price' => $this->productPrice->regularPrice,
        ];
    }
}
