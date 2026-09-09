<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto\Hosting;

readonly class SitebuilderSubscriptionPayload
{
    public function __construct(
        public string $startDate,
        public string $nextContractDate,
        public string $nextBillingDate,
        public int $contractPeriod,
        public int $billingPeriod,
        public string $slug,
        public string $referenceProductId,
        public string $referenceSubscriptionId,
        public SitebuilderBundleMigrationPayload $bundle,
        public string|null $domain = null,
        public string|null $extension = null,
    ) {
    }
}
