<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\DTO;

use Carbon\CarbonImmutable;
use Waterfront\Apps\API\Ferry\Enum\ImplementableProducts;

readonly class CreateSubscriptionDTO
{
    /**
     * @param array<int, non-empty-string>|null $labels
     */
    public function __construct(
        public ?string $domain,
        public ?string $extension,
        public string $slug,
        public int $billingPeriod,
        public int $contractPeriod,
        public ?int $fixedPrice,
        public bool $fixedPriceIsOneOff,
        public string $referenceSubscriptionId,
        public string $referenceProductId,
        public ?string $internalComment,
        public CarbonImmutable $startDate,
        public CarbonImmutable $nextContractDate,
        public CarbonImmutable $nextBillingDate,
        public ?CarbonImmutable $cancelDate,
        public ?array $labels,
        public CreateSubscriptionsDTO $createSubscriptions,
        public ImplementableProducts $implementableProduct,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'domain' => $this->domain,
            'extension' => $this->extension,
            'slug' => $this->slug,
            'billingPeriod' => $this->billingPeriod,
            'contractPeriod' => $this->contractPeriod,
            'fixedPrice' => $this->fixedPrice,
            'fixedPriceIsOneOff' => $this->fixedPriceIsOneOff,
            'referenceSubscriptionId' => $this->referenceSubscriptionId,
            'referenceProductId' => $this->referenceProductId,
            'internalComment' => $this->internalComment,
            'startDate' => $this->startDate,
            'nextContractDate' => $this->nextContractDate,
            'nextBillingDate' => $this->nextBillingDate,
            'cancelDate' => $this->cancelDate,
            'labels' => $this->labels,
        ];
    }
}
