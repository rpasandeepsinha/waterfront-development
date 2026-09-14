<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions;

use InvalidArgumentException;
use Ramsey\Uuid\Nonstandard\Uuid;
use Waterfront\Domain\Provision\DTO\ProvisioningResultQueryFilters;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class GatewayHelper
{
    public function __construct(
        private readonly ProvisionGateway $provisionGateway,
    ) {
    }

    public function hasSitebuilderDeploymentUsingGateway(Subscription $subscription): bool
    {
        // Only sitebuilder is supported for now. This can be adjusted with new types later.
        if (! $subscription->product->isSitebuilderProduct()) {
            throw new InvalidArgumentException('Only sitebuilder subscriptions are supported.');
        }

        $query = new ProvisioningResultQueryFilters(
            tag: Uuid::fromString($subscription->uuid),
            requestType: ProvisionType::SITEBUILDER,
        );
        $provisioningResults = $this->provisionGateway->fetch($query, 1);

        return ! $provisioningResults->isEmpty();
    }
}
