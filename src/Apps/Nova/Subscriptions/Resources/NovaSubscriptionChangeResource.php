<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Resources;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\Products\Resources\NovaProductResource;
use Waterfront\Apps\Nova\Subscriptions\Fields\NovaSubscriptionChangeStatusSelectField;
use Waterfront\Apps\Nova\Subscriptions\Fields\NovaSubscriptionChangeTypeSelectField;
use Waterfront\Apps\Nova\Subscriptions\Filters\NovaSubscriptionChangeStatusFilter;
use Waterfront\Apps\Nova\Subscriptions\Filters\NovaSubscriptionChangeTypeFilter;
use Waterfront\Domain\Subscriptions\Models\SubscriptionChange;

/** @property SubscriptionChange $resource */
class NovaSubscriptionChangeResource extends Resource
{
    public static string $model = SubscriptionChange::class;

    public static $globallySearchable = false;

    public static function getTranslationKey(): string
    {
        return 'upgrades-hosting';
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.subscription_changes');
    }

    /** @return array<int, Field> */
    public function fields(NovaRequest $request): array
    {
        return [
            BelongsTo::make(
                self::translate('subscription.attributes.domain'),
                'subscription',
                NovaSubscriptionResource::class
            )->searchable(),
            BelongsTo::make(
                self::translate('upgrades-hosting.attributes.from_product'),
                'fromProduct',
                NovaProductResource::class
            )->sortable()->searchable(),
            BelongsTo::make(
                self::translate('upgrades-hosting.attributes.to_product'),
                'toProduct',
                NovaProductResource::class
            )->sortable()->searchable(),
            NovaSubscriptionChangeTypeSelectField::makeForEditing(),
            Text::make(
                self::translate('subscription-change.attributes.type'),
                'type'
            )->exceptOnForms(),
            NovaSubscriptionChangeStatusSelectField::makeForEditing(),
            Text::make(
                self::translate('subscription-change.attributes.status'),
                'status'
            )->sortable()->exceptOnForms(),

            Date::make(self::translate('subscription-change.attributes.requested_at'), 'requested_at')
            ->required()
            ->rules('required'),
            Date::make(self::translate('subscription-change.attributes.completed_at'), 'completed_at'),
        ];
    }

    /** @return array<int, mixed> */
    public function filters(NovaRequest $request): array
    {
        return [
            resolve(NovaSubscriptionChangeStatusFilter::class),
            resolve(NovaSubscriptionChangeTypeFilter::class),
        ];
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return false;
    }

    public function authorizedToDelete(Request $request): bool
    {
        return true;
    }

    public function authorizedToForceDelete(Request $request): bool
    {
        return false;
    }

    public function authorizedToUpdate(Request $request): bool
    {
        return true;
    }
}
