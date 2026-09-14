<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Mappers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Waterfront\Domain\Customers\Models\MigratedSubscription;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Ferry\Dto\Hosting\MappableHostingPayload;
use Waterfront\Domain\Ferry\Serializers\FerrySerializerFactory;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class HostingMapper
{
    /**
     * @param Collection<int, Subscription> $subscriptions
     */
    public function mapSubscriptionsWithConnectionDetails(
        Collection $subscriptions,
        MappableHostingPayload $mappableHostingPayload,
    ): HostingMigrationPayload {
        $referenceSubscriptionId = $mappableHostingPayload->referenceSubscriptionId;

        if (! MigratedSubscription::query()->where('reference_subscription_id', $referenceSubscriptionId)->exists()) {
            throw new ModelNotFoundException(sprintf(
                'Migrated subscription with legacy subscription id "%s" does not exist',
                $referenceSubscriptionId,
            ));
        }

        $filteredSubscriptions = $subscriptions->filter(
            fn (Subscription $subscription): bool => (
                $subscription->migratedSubscriptions->contains('reference_subscription_id', $referenceSubscriptionId)
                && (
                    $subscription->hostingDeployment?->provider?->slug === ProviderSlug::PLACEHOLDER
                    || $subscription->resellerHostingDeployment?->provider->slug === ProviderSlug::PLACEHOLDER
                )
            ),
        );

        return HostingMigrationPayload::fromArray([
            'subscriptions' => $filteredSubscriptions,
            'referenceSubscriptionId' => $referenceSubscriptionId,
            'driver' => $mappableHostingPayload->driver,
            'server_name' => $mappableHostingPayload->hostname,
            'server_data' => $mappableHostingPayload->serverData,
        ]);
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     * @param array<mixed>                  $payloads
     *
     * @return array<int, MappableHostingPayload>
     */
    public function filterEligiblePayloads(Collection $subscriptions, array $payloads): array
    {
        $technicalPayloads = [];
        /** @var array<string, mixed> $payload */
        foreach ($payloads as $payload) {
            $referenceSubscriptionId = $payload['reference_subscription_id'];

            $existsInValidated = $subscriptions
                ->filter(
                    fn (Subscription $subscription) => (
                        $subscription->migratedSubscriptions()->first()?->reference_subscription_id
                        === $referenceSubscriptionId
                    ),
                )
                ->isNotEmpty();

            if ($existsInValidated) {
                $technicalPayloads[] = $payload;
            }
        }

        /** @var array<int, MappableHostingPayload> $mappablePayloads */
        $mappablePayloads = FerrySerializerFactory::getSerializer()->denormalize(
            $technicalPayloads,
            MappableHostingPayload::class . '[]',
        );

        return $mappablePayloads;
    }
}
