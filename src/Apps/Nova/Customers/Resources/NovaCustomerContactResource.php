<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Customers\Resources;

use Illuminate\Validation\Rule;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Domain\Customers\Enums\CustomerContactType;
use Waterfront\Domain\Customers\Models\CustomerContact;

/** @property CustomerContact $resource */
class NovaCustomerContactResource extends Resource
{
    public static string $model = CustomerContact::class;

    public static $displayInNavigation = false;

    /** @var array<mixed> */
    public static $search = [
        'first_name',
        'last_name',
        'company',
        'email',
    ];

    public static function getTranslationKey(): string
    {
        return 'customer.contact';
    }

    public function title(): string
    {
        return $this->resource->name;
    }

    public function subtitle(): string
    {
        return $this->resource->customer->name . ' - ' . $this->resource->customer->customer_number;
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        $contactTypes = [
            CustomerContactType::DEFAULT->value => self::translate('customer.contact.attributes.type.default'),
            CustomerContactType::TECHNICAL->value => self::translate('customer.contact.attributes.type.technical'),
            CustomerContactType::FINANCIAL->value => self::translate('customer.contact.attributes.type.financial'),
        ];

        return [
            BelongsTo::make(
                self::translate('customer.singular'),
                'customer',
                NovaCustomerResource::class,
            ),
            Text::make(self::translate('customer.attributes.first_name'), 'first_name')->required(),
            Text::make(self::translate('customer.attributes.last_name'), 'last_name')->required(),
            Text::make(self::translate('customer.contact.attributes.company'), 'company')->nullable(),
            Text::make(self::translate('customer.attributes.email'), 'email')
                ->rules('required', 'email', 'max:254')
                ->creationRules('unique:customer_contacts,email')
                ->updateRules('unique:customer_contacts,email,{{resourceId}}'),
            Select::make(self::translate('customer.contact.attributes.type'), 'type')
                ->options($contactTypes)
                ->displayUsingLabels()
                ->rules(['required', Rule::in(array_keys($contactTypes))])
                ->sortable(),
        ];
    }
}
