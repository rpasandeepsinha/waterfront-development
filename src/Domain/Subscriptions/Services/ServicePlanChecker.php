<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Services;

use Illuminate\Support\Facades\DB;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

readonly class ServicePlanChecker
{
    public function __construct(
        private ProductRepository $productRepository,
    ) {
    }

    public function isServicePlanActive(Subscription $subscription): bool
    {
        if (in_array($subscription->administrative_status, AdministrativeStatus::administrativelyEnded(), true)) {
            return false;
        }

        if ($this->productRepository->hasServicePlus($subscription->product)) {
            return true;
        }

        $uniqueProducts = [];
        foreach ($subscription->children as $child) {
            if (in_array($subscription->administrative_status, AdministrativeStatus::administrativelyEnded(), true)) {
                continue;
            }

            $uniqueProducts[$child->product->id] = $child->product;
        }

        return array_any($uniqueProducts, fn ($product) => $this->productRepository->hasServicePlus($product));
    }

    public function hasAccessToServicePlan(Customer $customer): bool
    {
        return DB::table('product_specs as ps')
            ->join('products as p', 'ps.product_id', '=', 'p.id')
            ->join('subscriptions as s', 'p.uuid', '=', 's.product_uuid')
            ->where('ps.name', ProductSpecName::HAS_SERVICE_PLUS->value)
            ->where('s.customer_id', $customer->id)
            ->whereNotIn('s.administrative_status', AdministrativeStatus::administrativelyEnded())
            ->exists();
    }

    /**
     * This method might be confusing at first glance. This method tells you if the customer has an active hosting subscription for
     * a product that does NOT have service plus. If this method returns true, it means the customer is eligible to order. In theory
     * the customer can have a child serviceplus subscription coupled to that hosting subscription, but that is not relevant for this check.
     * This check should always be used in combination with hasAccessToServicePlan. If hasAccessToServicePlan returns false, and this method returns true,
     * the customer should be able to order a service plan.
     */
    public function canOrderServicePlan(Customer $customer): bool
    {
        return DB::table('subscriptions as s')
            ->join('products as p', 's.product_uuid', '=', 'p.uuid')
            ->join('product_groups as pg', 'p.product_group_id', '=', 'pg.id')
            ->leftJoin('product_specs as ps', function ($join) {
                $join->on('p.id', '=', 'ps.product_id')->where(
                    'ps.name',
                    '=',
                    ProductSpecName::HAS_SERVICE_PLUS->value,
                )->whereIn('value', [true, 'true', '1', 1, 'yes']);
            })
            ->where('pg.slug', ProductGroupType::HOSTING->value)
            ->whereNull('ps.id')
            ->where('s.customer_id', $customer->id)
            ->whereNotIn('s.administrative_status', AdministrativeStatus::administrativelyEnded())
            ->exists();
    }
}
