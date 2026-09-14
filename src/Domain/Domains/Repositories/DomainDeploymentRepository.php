<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Repositories;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Domains\Models\DomainProviderBusinessUnit;
use Waterfront\Domain\Domains\Models\OpenproviderProviderCredentials;
use Waterfront\Domain\Domains\Models\RtrProviderCredentials;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\QueryBuilders\SubscriptionQueryBuilder;
use Waterfront\Infra\RtrClient\Services\Enums\DomainStatus;

class DomainDeploymentRepository
{
    public function getDomainProviderCredentials(
        ProviderSlug $providerSlug,
        DomainProviderBusinessUnit $businessUnit,
    ): RtrProviderCredentials|OpenproviderProviderCredentials {
        $credentialQueryBuilder = match ($providerSlug) {
            ProviderSlug::REALTIME_REGISTER => RtrProviderCredentials::query(),
            ProviderSlug::OPEN_PROVIDER => OpenproviderProviderCredentials::query(),
            default => throw new InvalidArgumentException(
                sprintf(
                    'Invalid provider type [%s] for domain provider credentials',
                    $providerSlug->value,
                ),
            ),
        };

        return $credentialQueryBuilder->where('domain_business_unit_id', $businessUnit->id)->firstOrFail();
    }

    public function getActiveDeploymentByDomain(string $domain): ?DomainDeployment
    {
        return DomainDeployment::query()
            ->whereHas('subscription', function (SubscriptionQueryBuilder $subscription) use ($domain) {
                $subscription->where('administrative_status', AdministrativeStatus::ACTIVE->value)->where(
                    'domain',
                    $domain,
                );
            })
            ->first();
    }

    public function getDeploymentByDomainAndCustomer(string $domain, Customer $customer): ?DomainDeployment
    {
        return DomainDeployment::query()
            ->whereHas(
                'subscription',
                fn (Builder $query) => $query->where('domain', $domain)->where('customer_id', $customer->id),
            )
            ->first();
    }

    public function getDomainDeploymentByDomain(string $domain): ?DomainDeployment
    {
        return DomainDeployment::query()
            ->whereHas(
                'subscription',
                fn (Builder $query) => $query->where('domain', $domain)->whereNotIn('administrative_status', [
                    ...AdministrativeStatus::administrativelyEnded(),
                    AdministrativeStatus::SUSPENDED->value,
                ]),
            )
            ->with('provider')
            ->first();
    }

    public function getDomainDeploymentByDomainIncludingSuspended(string $domain): ?DomainDeployment
    {
        return DomainDeployment::query()
            ->whereHas(
                'subscription',
                fn (Builder $query) => $query->where('domain', $domain)->whereNotIn(
                    'administrative_status',
                    AdministrativeStatus::administrativelyEnded(),
                ),
            )
            ->with('businessUnit')
            ->latest()
            ->first();
    }

    public function getDnsChildSubscription(Subscription $subscription): ?Subscription
    {
        /** @var ?Subscription $child */
        $child = $subscription
            ->children()
            ->whereHas('product.productGroup', function (Builder $productGroup) {
                $productGroup->where('slug', ProductGroupType::DNS);
            })
            ->first();

        return $child;
    }

    public function getActiveDomainDeploymentBySubscriptionUuidAndCustomer(
        Customer $customer,
        string $subscriptionUuid,
    ): ?DomainDeployment {
        return DomainDeployment::query()
            ->whereHas(
                'subscription',
                fn (Builder $query) => $query
                    ->where('uuid', $subscriptionUuid)
                    ->where('customer_id', $customer->id)
                    ->whereNotIn('administrative_status', [
                        ...AdministrativeStatus::administrativelyEnded(),
                        AdministrativeStatus::SUSPENDED->value,
                    ]),
            )
            ->with('provider')
            ->first();
    }

    public function updateProvider(DomainDeployment $domainDeployment, Provider $provider): void
    {
        $domainDeployment->provider_id = $provider->id;
        $domainDeployment->save();
    }

    public function getExtensionParentSubscription(Subscription $subscription): ?Subscription
    {
        /** @var ?Subscription $parent */
        $parent = $subscription
            ->parent()
            ->whereHas('product.productGroup', function (Builder $productGroup): void {
                $productGroup->where('slug', ProductGroupType::EXTENSION);
            })
            ->whereNotIn('administrative_status', [
                ...AdministrativeStatus::administrativelyEnded(),
                AdministrativeStatus::SUSPENDED->value,
            ])
            ->first();

        return $parent;
    }

    public function setDomainStatus(DomainDeployment $domainDeployment, DomainStatus $status): bool
    {
        $domainDeployment->domain_status = $status;

        return $domainDeployment->save();
    }
}
