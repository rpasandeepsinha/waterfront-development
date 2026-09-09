<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Resources;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\Subscriptions\Policies\NovaSubscriptionPolicy;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/**
 * @property Subscription $resource
 *
 * @see NovaSubscriptionPolicy::attachAnyTransfer()
 * @see NovaSubscriptionPolicy::attachTransfer()
 * @see NovaSubscriptionPolicy::detachTransfer()
 */
class NovaFreeSubscriptionResource extends NovaSubscriptionResource
{
    public static function label(): string
    {
        return self::translate('nova-resource-labels.free-subscriptions');
    }

    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query
            ->where('net_price', 0)
            ->where('gross_price', 0)
            ->whereHas(
                'product.productGroup',
                fn (Builder $productGroupQuery): Builder => $productGroupQuery->whereNot('slug', ProductGroupType::EXTENSION)
            );
    }
}
