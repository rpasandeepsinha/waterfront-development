<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Mappers;

use Illuminate\Support\Arr;
use Waterfront\Domain\Customers\Models\MigratedSubscription;
use Waterfront\Domain\Ferry\Dto\Domains\DomainMigrationPayload;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class DomainMapper
{
    /**
     * @param array<int, array<string, mixed>> $technicalPayloads
     */
    public function mapDomainWithTechnicalPayload(
        Subscription $subscription,
        array $technicalPayloads,
    ): ?DomainMigrationPayload {
        /** @var MigratedSubscription $migratedSubscription */
        $migratedSubscription = $subscription->migratedSubscriptions()->firstOrFail();
        $referenceSubscriptionId = $migratedSubscription->reference_subscription_id;

        if ($referenceSubscriptionId === null) {
            return null;
        }

        foreach ($technicalPayloads as $technicalPayload) {
            if (
                array_key_exists('reference_subscription_id', $technicalPayload)
                && $technicalPayload['reference_subscription_id'] === $referenceSubscriptionId
            ) {
                /** @var string|null $referenceDnsTemplateId */
                $referenceDnsTemplateId = Arr::get($technicalPayload, 'domain_data.reference_dns_template_id');

                /** @var string $stringedDriver */
                $stringedDriver = Arr::get(
                    $technicalPayload,
                    'driver',
                    ProviderSlug::REALTIME_REGISTER->value,
                );

                $driver = ProviderSlug::from($stringedDriver);

                /** @var ?string $businessUnitSlug */
                $businessUnitSlug = Arr::get($technicalPayload, 'reference_domain_provider_business_unit_slug');

                return new DomainMigrationPayload(
                    referenceDnsTemplateId: $referenceDnsTemplateId,
                    referenceSubscriptionId: $referenceSubscriptionId,
                    driver: $driver,
                    referenceDomainProviderBusinessUnitSlug: $businessUnitSlug,
                );
            }
        }

        return null;
    }
}
