<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Clients;

use Ramsey\Uuid\UuidInterface;

class AcronisClient
{
    public function __construct(
        public readonly UuidInterface $tenantId,
        public readonly AcronisUserClient $userClient,
        public readonly AcronisOfferingItemsClient $offeringItemsClient,
        public readonly AcronisTenantClient $tenantClient,
        public readonly AcronisGenericClient $genericClient,
    ) {
    }
}
