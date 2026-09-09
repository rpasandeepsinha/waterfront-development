<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Resources;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\Subscriptions\Policies\NovaSubscriptionPolicy;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/**
 * @property Subscription $resource
 *
 * @see NovaSubscriptionPolicy::attachAnyTransfer()
 * @see NovaSubscriptionPolicy::attachTransfer()
 * @see NovaSubscriptionPolicy::detachTransfer()
 */
class NovaExpiredSubscriptionResource extends NovaSubscriptionResource
{
    public static function label(): string
    {
        return self::translate('subscription.expired');
    }

    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query
            ->whereIn('administrative_status', [AdministrativeStatus::EXPIRED->value, AdministrativeStatus::INACTIVE->value]);
    }
}
