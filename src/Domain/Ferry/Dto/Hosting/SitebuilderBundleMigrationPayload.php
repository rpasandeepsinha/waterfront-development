<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto\Hosting;

use Illuminate\Support\Collection;
use Waterfront\Domain\Subscriptions\Models\Subscription;

readonly class SitebuilderBundleMigrationPayload
{
    public HostingMigrationPayload $mailOnly;

    public HostingMigrationPayload $sitebuilder;

    /**
     * @param Collection<int, Subscription> $subscriptions
     * @param array<string, mixed>          $mailOnly
     * @param array<string, mixed>          $sitebuilder
     */
    public function __construct(
        array $mailOnly,
        array $sitebuilder,
        public Collection $subscriptions = new Collection(),
    ) {
        // Temporary solution until we use the serializer fully
        $this->mailOnly = HostingMigrationPayload::fromArray([
            'subscriptions' => $subscriptions,
            'referenceSubscriptionId' => $mailOnly['reference_subscription_id'],
            'driver' => $mailOnly['driver'],
            'server_name' => $mailOnly['hostname'],
            'server_data' => $mailOnly['server_data'],
        ]);

        $this->sitebuilder = HostingMigrationPayload::fromArray([
            'subscriptions' => $subscriptions,
            'referenceSubscriptionId' => $sitebuilder['reference_subscription_id'],
            'driver' => $sitebuilder['driver'],
            'server_name' => $sitebuilder['hostname'],
            'server_data' => $sitebuilder['server_data'],
        ]);
    }
}
