<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365OrderStatus;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSlug;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class Microsoft365Repository
{
    private const array PRODUCT_SPEC_TRUE_VALUES = [true, 'true', '1', 1, 'yes'];

    public function hasMicrosoft365Subscriptions(Customer $customer): bool
    {
        return Subscription::query()->whereProductGroupType(ProductGroupType::MICROSOFT_365)
            ->where('customer_id', $customer->id)
            ->whereNotIn('administrative_status', [...AdministrativeStatus::administrativelyEnded(), AdministrativeStatus::ARCHIVING->value])
            // The 'parent_subscription_id' is null when it is a parent (read: administrative) subscription.
            ->whereNull('parent_subscription_id')
            ->exists();
    }

    /**
     * @param array<int> $productIds
     *
     * @return Collection<string, stdClass> Key string like '{product_id}{contract_period}{billing_period}'
     */
    public function getActiveSubscriptionsInfo(Customer $customer, array $productIds): Collection
    {
        /**
         * A raw result query is used here as customers can have several thousands of subscriptions.
         * Loading, looping, parsing them to get to the result set the query below does is very
         * resource- and time-consuming. It already has broken the process the past that way.
         */

        /** @var Collection<int, stdClass> $res */
        $res = DB::table('subscriptions', 's')
            ->select(
                Db::raw('DATE(MAX(s.next_billing_date)) AS next_billing_date'),
                's.contract_period',
                's.billing_period',
                'products.id AS product_id'
            )
            ->join('products', 's.product_uuid', '=', 'products.uuid')
            ->join('product_groups', 'products.product_group_id', '=', 'product_groups.id')
            ->where('product_groups.slug', '=', ProductGroupType::MICROSOFT_365)
            ->where('s.customer_id', $customer->id)
            ->whereNotIn('administrative_status', [...AdministrativeStatus::administrativelyEnded(), AdministrativeStatus::ARCHIVING->value])
            ->whereIn('products.id', $productIds)
            ->groupBy(
                's.contract_period',
                's.billing_period',
                'products.id'
            )
            ->get()
        ;

        return $res->mapWithKeys(
            fn (stdClass $row) => [$row->product_id . $row->contract_period . $row->billing_period => $row]
        );
    }

    /**
     * Find Microsoft 365 subscriptions where the parent subscriptions and child subscriptions are all deleted.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Microsoft365Deployment>
     */
    public function findMicrosoft365ParentAndChildSubscriptionsDeleted(): Collection
    {
        return Microsoft365Deployment::query()
            ->select('microsoft365_deployments.*')
            ->leftJoin('subscriptions', 'subscriptions.id', '=', 'microsoft365_deployments.subscription_id')
            ->where('kpn_status', '<>', Microsoft365OrderStatus::TERMINATED)
            ->where('subscriptions.administrative_status', AdministrativeStatus::ARCHIVED->value)
            ->get();
    }

    /**
     * Find subscriptions from the Microsoft 365 product_group which are not coupled to any Microsoft365 subscriptions.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Subscription>
     */
    public function findGhostMicrosoft365Subscriptions(): Collection
    {
        return Subscription::query()
            ->select('subscriptions.id')
            ->join('products', 'subscriptions.product_uuid', '=', 'products.uuid')
            ->join('product_groups', 'products.product_group_id', '=', 'product_groups.id')
            ->leftJoin('microsoft365_deployments', 'microsoft365_deployments.subscription_id', '=', 'subscriptions.id')
            ->where('product_groups.slug', '=', ProductGroupType::MICROSOFT_365)
            ->where('subscriptions.administrative_status', '<>', AdministrativeStatus::ARCHIVED->value)
            ->whereNull('subscriptions.parent_subscription_id')
            ->whereNull('microsoft365_deployments.id')
            ->get();
    }

    /**
     * Find subscriptions from the Microsoft 365 product_group where the child product is coupled to the Microsoft365 subscriptions.
     *
     * @return Collection<int, Subscription>
     */
    public function findSeatProductMicrosoft365Subscriptions(): Collection
    {
        return Subscription::query()
            ->select('subscriptions.id')
            ->join('products', 'subscriptions.product_uuid', '=', 'products.uuid')
            ->join('microsoft365_deployments', 'microsoft365_deployments.subscription_id', '=', 'subscriptions.id')
            ->where('products.slug', 'like', 'microsoft-%')
            ->where('products.slug', 'not like', 'microsoft-%-parent')
            ->where('subscriptions.administrative_status', '<>', AdministrativeStatus::ARCHIVED->value)
            ->whereNull('subscriptions.parent_subscription_id')
            ->get();
    }

    public function activeChildrenCount(Microsoft365Deployment $microsoft365Deployment): int
    {
        return $microsoft365Deployment->subscriptionChildren
            ->where('administrative_status', AdministrativeStatus::ACTIVE->value)
            ->count();
    }

    public function findPendingCopilotDeploymentForRetry(Microsoft365CustomerInfo $microsoft365CustomerInfo): ?Microsoft365Deployment
    {
        return Microsoft365Deployment::query()
            ->where('microsoft365_customer_info_id', $microsoft365CustomerInfo->id)
            ->where('kpn_status', Microsoft365OrderStatus::PLACED->value)
            ->whereNull('kpn_order_id')
            ->whereRelation('subscription.product', 'slug', ProductSlug::MICROSOFT_COPILOT_PARENT->value)
            ->whereHas(
                'subscriptionChildren',
                fn (Builder $query): Builder => $query
                    ->where('administrative_status', AdministrativeStatus::ACTIVE->value)
            )
            ->with(['subscription.product', 'microsoft365CustomerInfo.customer', 'subscriptionChildren'])
            ->first();
    }

    public function hasActiveCopilotPrerequisite(Microsoft365CustomerInfo $microsoft365CustomerInfo): bool
    {
        return Microsoft365Deployment::query()
            ->where('microsoft365_customer_info_id', $microsoft365CustomerInfo->id)
            ->where('kpn_status', Microsoft365OrderStatus::ACTIVE->value)
            ->whereHas(
                'subscriptionChildren',
                fn (Builder $query): Builder => $query
                    ->where('administrative_status', AdministrativeStatus::ACTIVE->value)
                    ->whereHas(
                        'product.productSpecs',
                        fn (Builder $query): Builder => $query
                            ->where('name', ProductSpecName::MICROSOFT365_ALLOW_COPILOT->value)
                            ->whereIn('value', self::PRODUCT_SPEC_TRUE_VALUES)
                    )
            )
            ->exists();
    }

    /**
     * @return Collection<int, Microsoft365Deployment>
     */
    public function getDeploymentsByCustomerAndDomainName(Customer $customer, ?string $domain): Collection
    {
        return Microsoft365Deployment::query()
            ->whereHas(
                'microsoft365CustomerInfo',
                fn (Builder $query) => $query
                    ->where('customer_id', $customer->id)
                    ->where('primary_domain', $domain)
            )
            ->get();
    }
}
