<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Customers\Resources;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Country;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Domain\Customers\Models\CustomerAddress;

/** @property CustomerAddress $resource */
class NovaCustomerAddressResource extends Resource
{
    public static string $model = CustomerAddress::class;

    public static $displayInNavigation = false;

    /**
     * @var array<mixed>
     */
    public static $search = [
        'street_name',
        'city',
    ];

    public static function getTranslationKey(): string
    {
        return 'customer.address';
    }

    public function title(): string
    {
        return $this->resource->street_name;
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            BelongsTo::make(
                self::translate('customer.singular'),
                'customer',
                NovaCustomerResource::class
            ),
            Text::make(self::translate('customer.address.attributes.street_name'), 'street_name'),
            Text::make(self::translate('customer.address.attributes.street_number'), 'street_number'),
            Text::make(self::translate('customer.address.attributes.street_number_addition'), 'street_number_addition')->nullable(),
            Text::make(self::translate('customer.address.attributes.zip_code'), 'zip_code'),
            Text::make(self::translate('customer.address.attributes.city'), 'city'),
            Country::make(self::translate('customer.address.attributes.country'), 'country_code'),
            Text::make(self::translate('customer.address.attributes.type'), 'type')->nullable()->readonly(),
        ];
    }

    public function authorizedToDelete(Request $request): bool
    {
        return false;
    }

    public function authorizedToForceDelete(Request $request): bool
    {
        return false;
    }
}
