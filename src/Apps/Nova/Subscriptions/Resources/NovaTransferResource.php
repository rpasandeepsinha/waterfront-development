<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Resources;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\Customers\Resources\NovaCustomerResource;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\Subscriptions\Policies\NovaTransferPolicy;
use Waterfront\Domain\Transfers\Models\Transfer;

/**
 * @property Transfer $resource
 *
 * @see NovaTransferPolicy::attachSubscription()
 * @see NovaTransferPolicy::attachAnySubscription()
 * @see NovaTransferPolicy::detachSubscription()
 */
class NovaTransferResource extends Resource
{
    public static string $model = Transfer::class;

    public static function getTranslationKey(): string
    {
        return 'transfer';
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.transfers');
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            BelongsTo::make(
                self::translate('transfer.relations.from_customer'),
                'fromCustomer',
                NovaCustomerResource::class
            )->sortable(),
            BelongsTo::make(
                self::translate('transfer.relations.to_customer'),
                'toCustomer',
                NovaCustomerResource::class
            )->sortable(),
            Text::make(
                self::translate('transfer.attributes.status'),
                'Transferstatus'
            ),
            BelongsToMany::make(
                self::translate('transfer.relations.subscriptions'),
                'subscriptions',
                NovaSubscriptionResource::class
            ),
        ];
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return false;
    }

    public static function authorizable(): bool
    {
        return true;
    }
}
