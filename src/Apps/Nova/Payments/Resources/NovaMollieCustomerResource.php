<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Payments\Resources;

use Illuminate\Http\Request;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\Customers\Resources\NovaCustomerResource;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\Payments\Actions\NovaFetchMollieCustomerAction;
use Waterfront\Apps\Nova\Payments\Actions\NovaFindMandateAction;
use Waterfront\Apps\Nova\Payments\Actions\NovaUpdateMollieCustomerAction;
use Waterfront\Domain\Payments\Models\MollieCustomer;

/**
 * @property MollieCustomer $resource
 */
class NovaMollieCustomerResource extends Resource
{
    public static string $model = MollieCustomer::class;

    public static $globallySearchable = false;

    public static $displayInNavigation = false;

    /**
     * @var array<mixed>
     */
    public static $with = ['customer', 'mandates'];

    public static function getTranslationKey(): string
    {
        return 'mollie-customer';
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.mollie_customers');
    }

    public function title(): string
    {
        return $this->resource->mollie_customer_reference_id;
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make('ID')->sortable(),
            Text::make('mollie_customer_reference_id'),
            BelongsTo::make(
                self::translate('customer.singular'),
                'customer',
                NovaCustomerResource::class,
            ),
            HasMany::make(
                self::translate('mandate.singular'),
                'mandates',
                NovaMandateResource::class,
            )->onlyOnDetail(),
            DateTime::make(self::translate('nova-resource-labels.created-at'), 'created_at')->onlyOnDetail(),
            DateTime::make(self::translate('nova-resource-labels.updated-at'), 'updated_at')->onlyOnDetail(),
        ];
    }

    public function authorizedToView(Request $request): bool
    {
        return true;
    }

    public function authorizedToUpdate(Request $request): bool
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

    public function authorizedToDeleteForSerialization(Request $request): bool
    {
        return false;
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return false;
    }

    /**
     * @return array<int, Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            resolve(NovaFindMandateAction::class),
            resolve(NovaFetchMollieCustomerAction::class),
            resolve(NovaUpdateMollieCustomerAction::class)->onlyOnDetail(),
        ];
    }
}
