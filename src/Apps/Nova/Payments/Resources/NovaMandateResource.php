<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Payments\Resources;

use Illuminate\Http\Request;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\Payments\Actions\NovaFetchMandateAction;
use Waterfront\Apps\Nova\Payments\Actions\NovaRevokeMandateAction;
use Waterfront\Domain\Payments\Models\Mandate;

/**
 * @property Mandate $resource
 */
class NovaMandateResource extends Resource
{
    public static string $model = Mandate::class;

    public static $globallySearchable = false;

    public static $displayInNavigation = false;

    /**
     * @var array<mixed>
     */
    public static $with = ['mollieCustomer'];

    public static function getTranslationKey(): string
    {
        return 'mandate';
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.mandates');
    }

    public function title(): string
    {
        return $this->resource->mollie_mandate_reference_id . ' - ' . $this->resource->payt_mandate_reference_id;
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make('ID')->sortable(),

            BelongsTo::make(
                'Mollie customer',
                'mollieCustomer',
                NovaMollieCustomerResource::class,
            ),

            Text::make('Mollie mandate reference ID', 'mollie_mandate_reference_id'),
            Text::make('Payt mandate reference ID', 'payt_mandate_reference_id'),
            Text::make('method'),

            Date::make(self::translate('nova-resource-labels.signature-date'), 'signature_date'),
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
            resolve(NovaFetchMandateAction::class),
            resolve(NovaRevokeMandateAction::class)->onlyOnDetail(),
        ];
    }
}
