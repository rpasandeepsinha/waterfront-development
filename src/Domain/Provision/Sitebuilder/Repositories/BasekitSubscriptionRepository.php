<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Repositories;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class BasekitSubscriptionRepository
{
    public function countBasekitSubscriptions(): int
    {
        return (clone $this->basekitSubscriptionQuery())->count();
    }

    /**
     * @param Closure(Collection<int, Subscription>): bool $callback
     */
    public function chunkBasekitSubscriptions(Closure $callback): void
    {
        (clone $this->basekitSubscriptionQuery())->chunk(
            200,
            /**
             * @param Collection<int, Subscription> $subscriptions
             */
            static fn (Collection $subscriptions): bool => $callback($subscriptions),
        );
    }

    /**
     * @return Builder<Subscription>
     */
    private function basekitSubscriptionQuery(): Builder
    {
        return Subscription::query()
            ->with([
                'customer',
                'product.productGroup',
                'product.productSpecs',
                'hostingDeployment.sitebuilderProvider',
                'provisionSitebuilderDeployment.basekitDeployment',
            ])
            ->whereHas('customer', function (Builder $query): void {
                $query->where(function (Builder $q): void {
                    $q->where('email', 'like', '%@yourhosting.nl')->orWhere('email', 'like', '%@sandwave.io');
                });
            })
            ->whereHas('product.productGroup', function (Builder $query): void {
                $query->where('slug', ProductGroupType::HOSTING);
            })
            ->whereHas('hostingDeployment.sitebuilderProvider', function (Builder $query): void {
                $query->where('slug', ProviderSlug::BASEKIT);
            })
            ->whereDoesntHave('provisionSitebuilderDeployment');
    }
}
