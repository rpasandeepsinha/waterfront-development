<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Domains\Resources;

use Illuminate\Http\Request;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean as NovaBoolField;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\Customers\Resources\NovaCustomerResource;
use Waterfront\Apps\Nova\Domains\Actions\NovaChangeRTRContactHandleAction;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Domain\Domains\Models\DomainContact;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;

/** @property DomainContact $resource */
class NovaDomainContactResource extends Resource
{
    public static string $model = DomainContact::class;

    public static $displayInNavigation = false;

    public static $globallySearchable = false;

    /** @var array<mixed> */
    public static $search = [
        'id',
    ];

    public static function getTranslationKey(): string
    {
        return 'domain-contact';
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make(self::translate('domain-contact.attributes.email'), 'email'),
            Text::make(
                self::translate('domain-contact.attributes.external_handle'),
                'id',
                fn (): string => $this->resource
                    ->providers()
                    ->where('type', ProviderType::DOMAIN)
                    ->get()
                    ->implode(function (Provider $provider): ?string {
                        $externalContact = $provider->pivot->external_contact;
                        assert(is_string($externalContact) || is_null($externalContact));

                        if ($externalContact === null) {
                            return null;
                        }

                        return $externalContact . " ({$provider->slug->value})";
                    }, ', '),
            )->exceptOnForms(),
            Text::make(self::translate('domain-contact.attributes.first_name'), 'first_name')->onlyOnDetail(),
            Text::make(self::translate('domain-contact.attributes.last_name'), 'last_name')->onlyOnDetail(),
            Text::make(
                self::translate('domain-contact.attributes.phone_country_code'),
                'phone_country_code',
            )->onlyOnDetail(),
            Text::make(self::translate('domain-contact.attributes.phone_area_code'), 'phone_area_code')->onlyOnDetail(),
            Text::make(
                self::translate('domain-contact.attributes.phone_subscriber_number'),
                'phone_subscriber_number',
            )->onlyOnDetail(),
            Text::make(self::translate('domain-contact.attributes.street_name'), 'street_name')->onlyOnDetail(),
            Text::make(self::translate('domain-contact.attributes.street_number'), 'street_number')->onlyOnDetail(),
            Text::make(self::translate('domain-contact.attributes.zip_code'), 'zip_code')->onlyOnDetail(),
            Text::make(self::translate('domain-contact.attributes.city'), 'city')->onlyOnDetail(),
            Text::make(self::translate('domain-contact.attributes.country_code'), 'country_code')->onlyOnDetail(),
            Text::make(self::translate('domain-contact.attributes.organization'), 'organization')->onlyOnDetail(),
            NovaBoolField::make(
                self::translate('domain-contact.attributes.default_owner'),
                'default_owner',
            )->onlyOnDetail(),
            BelongsTo::make(
                self::translate('user.relations.customer'),
                'customer',
                NovaCustomerResource::class,
            )->onlyOnDetail(),
        ];
    }

    /** @return array<int, Action> */
    public function actions(NovaRequest $request): array
    {
        return [
            resolve(NovaChangeRTRContactHandleAction::class),
        ];
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
}
