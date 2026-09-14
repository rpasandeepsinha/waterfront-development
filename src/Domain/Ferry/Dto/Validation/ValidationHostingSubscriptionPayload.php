<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto\Validation;

readonly class ValidationHostingSubscriptionPayload
{
    /**
     * @param array<string, int|string|null> $serverData
     */
    public function __construct(
        public string $startDate,
        public string $nextContractDate,
        public string $nextBillingDate,
        public int $contractPeriod,
        public int $billingPeriod,
        public string $slug,
        public string $referenceProductId,
        public string $referenceSubscriptionId,
        public string $driver,
        public string $hostname,
        public array $serverData,
        public ?string $domain = null,
        public ?string $extension = null,
    ) {
    }
}
