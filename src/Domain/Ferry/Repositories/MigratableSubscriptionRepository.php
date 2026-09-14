<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Waterfront\Apps\API\Ferry\Enum\ProductNotAllowedToMigrate;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Customers\Models\MigratedSubscription;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Sitebuilder\SitebuilderService;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class MigratableSubscriptionRepository
{
    public function __construct(
        private readonly SitebuilderService $sitebuilderService,
    ) {
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getSubscriptionsForDomainContactMigration(Customer $customer): Collection
    {
        return $customer
            ->subscriptions()
            ->with([
                'domainDeployment',
                'domainDeployment.provider',
                'domainDeployment.contactOwner',
                'domainDeployment.contactOwner.providers',
                'migratedSubscriptions',
                'product',
                'product.productGroup',
            ])
            ->whereHas('product.productGroup', fn (Builder $query) => $query->where(
                'slug',
                ProductGroupType::EXTENSION,
            ))
            ->whereHas('migratedSubscriptions')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getSubscriptionsForConfigureDnsMigration(Customer $customer): Collection
    {
        return $customer
            ->subscriptions()
            ->with([
                'domainDeployment',
                'domainDeployment.provider',
                'migratedSubscriptions',
                'product',
                'product.productGroup',
            ])
            ->whereDoesntHave('product', fn (Builder $query) => $query->where(
                'slug',
                ProductNotAllowedToMigrate::FREE_DNS->value,
            ))
            ->whereHas('product.productGroup', fn (Builder $query) => $query->whereIn('slug', [
                ProductGroupType::EXTENSION,
                ProductGroupType::DNS,
            ]))
            ->whereHas('migratedSubscriptions')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getSubscriptionsForRedirectMigration(Customer $customer): Collection
    {
        return $customer
            ->subscriptions()
            ->with([
                'migratedSubscriptions',
                'product',
                'product.productGroup',
            ])
            ->whereHas('product.productGroup', fn (Builder $query) => $query->where('slug', ProductGroupType::REDIRECT))
            ->whereHas('migratedSubscriptions')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getSubscriptionsForNameserverMigration(Customer $customer): Collection
    {
        return $customer
            ->subscriptions()
            ->with([
                'domainDeployment',
                'domainDeployment.provider',
                'migratedSubscriptions',
                'product',
                'product.productGroup',
            ])
            ->whereHas('product.productGroup', fn (Builder $query) => $query->where(
                'slug',
                ProductGroupType::EXTENSION,
            ))
            ->whereHas('migratedSubscriptions')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getSubscriptionsForBackupMigration(Customer $customer): Collection
    {
        return $customer
            ->subscriptions()
            ->with([
                'migratedSubscriptions',
                'product',
                'product.productGroup',
            ])
            ->whereHas('product.productGroup', fn (Builder $query) => $query->where('slug', ProductGroupType::BACKUP))
            ->whereHas('migratedSubscriptions')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getSubscriptionsForHostingMigration(Customer $customer): Collection
    {
        return $customer
            ->subscriptions()
            ->with([
                'hostingDeployment',
                'hostingDeployment.provider',
                'product.productSpecs',
                'migratedSubscriptions',
                'product',
                'product.productGroup',
            ])
            ->whereHas('product.productGroup', fn (Builder $query) => $query->where('slug', ProductGroupType::HOSTING))
            ->whereHas('hostingDeployment.provider')
            ->whereDoesntHave('hostingDeployment.mailProvider')
            ->whereDoesntHave('hostingDeployment.sitebuilderProvider')
            ->whereHas('migratedSubscriptions')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getSubscriptionsForResellerHostingMigration(Customer $customer): Collection
    {
        return $customer
            ->subscriptions()
            ->with([
                'resellerHostingDeployment',
                'resellerHostingDeployment.provider',
                'product.productSpecs',
                'migratedSubscriptions',
                'product',
                'product.productGroup',
            ])
            ->whereHas('product.productGroup', fn (Builder $query) => $query->where(
                'slug',
                ProductGroupType::RESELLER_HOSTING,
            ))
            ->whereHas('resellerHostingDeployment.provider')
            ->whereHas('migratedSubscriptions')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getSubscriptionsForMailOnlyMigration(Customer $customer): Collection
    {
        return $customer
            ->subscriptions()
            ->with([
                'hostingDeployment',
                'hostingDeployment.provider',
                'hostingDeployment.mailProvider',
                'product.productSpecs',
                'migratedSubscriptions',
                'product',
                'product.productGroup',
            ])
            ->whereHas('product.productGroup', fn (Builder $query) => $query->where('slug', ProductGroupType::HOSTING))
            ->whereHas('hostingDeployment.mailProvider')
            ->whereDoesntHave('hostingDeployment.provider')
            ->whereDoesntHave('hostingDeployment.sitebuilderProvider')
            ->whereHas('migratedSubscriptions')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getSubscriptionsForSitebuilderMigration(Customer $customer): Collection
    {
        $isGatewaySitebuilder = $this->sitebuilderService->hasSitebuilderThroughGateway($customer->email);

        $query = $customer
            ->subscriptions()
            ->with([
                'hostingDeployment',
                'hostingDeployment.provider',
                'hostingDeployment.mailProvider',
                'hostingDeployment.sitebuilderProvider',
                'migratedSubscriptions',
                'product',
                'product.productGroup',
            ])
            ->whereHas('product.productGroup', fn (Builder $query) => $query->where('slug', ProductGroupType::HOSTING))
            ->whereDoesntHave('hostingDeployment.provider')
            ->whereHas('hostingDeployment.mailProvider')
            ->whereHas('migratedSubscriptions');

        if (! $isGatewaySitebuilder) {
            $query->whereHas('hostingDeployment.sitebuilderProvider');
        }

        return $query->orderBy('id')->get();
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getSubscriptionsForSslMigration(Customer $customer): Collection
    {
        return $customer
            ->subscriptions()
            ->with([
                'sslDeployment',
                'sslDeployment.provider',
                'product.productGroup',
                'migratedSubscriptions',
                'product',
            ])
            ->whereHas('product.productGroup', fn (Builder $query) => $query->where('slug', ProductGroupType::SSL))
            ->whereHas('migratedSubscriptions')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getSubscriptionsForEnableDnsSecMigration(Customer $customer): Collection
    {
        return $this->getSubscriptionsForNameserverMigration($customer);
    }

    public function isSubscriptionAlreadyCreated(
        string $subscriptionReferenceId,
        string $productReferenceId,
        ?string $bu,
    ): bool {
        return MigratedSubscription::query()
            ->when(
                $bu !== null,
                fn (Builder $query) => $query->whereHas('migratedCustomers', fn (Builder $query) => $query->where(
                    'reference_name',
                    $bu,
                )),
            )
            ->where([
                'reference_subscription_id' => $subscriptionReferenceId,
                'reference_product_id' => $productReferenceId,
            ])
            ->exists();
    }

    public function getAlreadyExistingSubscriptionFromMigration(
        string $subscriptionReferenceId,
        string $productReferenceId,
        string $productSlug,
    ): Subscription {
        return Subscription::whereHas('migratedSubscriptions', fn (Builder $query) => $query->where([
            'reference_subscription_id' => $subscriptionReferenceId,
            'reference_product_id' => $productReferenceId,
        ]))
            ->whereHas('product', fn (Builder $query) => $query->where('slug', $productSlug))
            ->firstOrFail();
    }

    public function getAlreadyExistingFreeDnsSubscription(string $domain): Subscription
    {
        return Subscription::whereProductGroupType(ProductGroupType::DNS)->where('domain', $domain)->firstOrFail();
    }

    public function getBuOriginNameFromSubscription(Subscription $subscription): string
    {
        /** @var MigratedSubscription $migrationSubscription */
        $migrationSubscription = $subscription->migratedSubscriptions()->firstOrFail();

        /** @var MigratedCustomer $migratedCustomer */
        $migratedCustomer = $migrationSubscription->migratedCustomers()->firstOrFail();

        return $migratedCustomer->reference_name;
    }
}
