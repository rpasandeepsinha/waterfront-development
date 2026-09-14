<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Migrations\Resources;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\Subscriptions\Resources\NovaSubscriptionResource;
use Waterfront\Domain\Customers\Models\MigratedSubscription;

/** @property MigratedSubscription $resource */
class NovaMigratedSubscriptionResource extends Resource
{
    public static string $model = MigratedSubscription::class;

    public static $displayInNavigation = false;

    public static $globallySearchable = false;

    /**
     * @var array<mixed>
     */
    public static $with = ['migratedCustomers'];

    public static function getTranslationKey(): string
    {
        return 'migrated.subscription';
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request)
    {
        return [
            ID::make(self::translate('ID'), 'id')->sortable()->hideFromDetail(),
            Text::make(
                self::translate('migrated.subscription.reference_name'),
                fn (): string => implode(
                    ', ',
                    $this->resource->migratedCustomers()->pluck('reference_name')->toArray(),
                ),
            ),
            Text::make(
                self::translate('migrated.subscription.group_type'),
                fn (): string => implode(', ', $this->resource->migratedCustomers()->pluck('group_type')->toArray()),
            ),
            Text::make(self::translate('migrated.subscription.reference_subscription_id'), 'reference_subscription_id'),
            Text::make(self::translate('migrated.subscription.reference_product_id'), 'reference_product_id'),
            DateTime::make(self::translate('migrated.subscription.created_at'), 'created_at'),
            HasMany::make(
                self::translate('nova-resource-labels.customers'),
                'migratedCustomers',
                NovaMigratedCustomerResource::class,
            ),
            HasMany::make(
                self::translate('subscription.plural'),
                'subscriptions',
                NovaSubscriptionResource::class,
            ),
        ];
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return false;
    }

    public function authorizedToDelete(Request $request): bool
    {
        return false;
    }

    public function authorizedToForceDelete(Request $request): bool
    {
        return false;
    }

    public function authorizedToUpdate(Request $request): bool
    {
        return false;
    }

    public function authorizedToView(Request $request): bool
    {
        return true;
    }
}
