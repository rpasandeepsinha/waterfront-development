<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Hosting\Resources;

use Illuminate\Http\Request;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\Hosting\Actions\NovaFetchUserFromResellerSubscription;
use Waterfront\Apps\Nova\Subscriptions\Resources\NovaSubscriptionResource;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;

/** @property ResellerHostingDeployment $resource */
class NovaResellerHostingDeploymentResource extends Resource
{
    public static string $model = ResellerHostingDeployment::class;

    public static $displayInNavigation = false;

    public static $globallySearchable = false;

    /**
     * @var array<mixed>
     */
    public static $search = [
        'id',
    ];

    public static function getTranslationKey(): string
    {
        return 'reseller-hosting-subscription';
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make(
                self::translate('subscription.reseller-hosting-subscription.internal-subscription'),
                'subscription',
                NovaSubscriptionResource::class
            )
                ->sortable()
                ->exceptOnForms(),

            Text::make(
                self::translate('subscription.reseller-hosting-subscription.directadmin-username'),
                'directadmin_customer_username'
            )
                ->nullable()
                ->showOnDetail($this->resource->directadmin_customer_username !== null),

            Text::make(
                self::translate('subscription.reseller-hosting-subscription.plesk-username'),
                'plesk_customer_username'
            )
                ->nullable()
                ->showOnDetail($this->resource->plesk_customer_username !== null),

            Number::make(
                self::translate('subscription.reseller-hosting-subscription.customer-id'),
                'plesk_customer_id'
            )
                ->hideFromIndex()
                ->onlyOnForms()
                ->nullable(),

            BelongsTo::make(
                self::translate('subscription.reseller-hosting-subscription.server'),
                'server',
                NovaServerResource::class
            )
                ->sortable(),

            Select::make(
                self::translate('subscription.reseller-hosting-subscription.provider'),
                'provider_id'
            )
                ->options(function (): array {
                    $providers = Provider::where('type', ProviderType::HOSTING)->get(['id', 'slug']);
                    return array_column($providers->toArray(), 'slug', 'id');
                })
                ->displayUsingLabels()
                ->sortable(),

            Text::make(
                self::translate('subscription.reseller-hosting-subscription.storage-type'),
                'storage_type'
            )
                ->hideFromIndex()
                ->onlyOnForms(),

            Number::make(
                self::translate('subscription.reseller-hosting-subscription.disk-space'),
                'disk_space'
            )
                ->nullable()
                ->hideFromIndex()
                ->onlyOnForms(),

            Number::make(
                self::translate('subscription.reseller-hosting-subscription.max-users'),
                'max_users'
            )
                ->nullable()
                ->hideFromIndex()
                ->onlyOnForms(),

            Number::make(
                self::translate('subscription.reseller-hosting-subscription.max-domains'),
                'max_domains'
            )
                ->nullable()
                ->hideFromIndex()
                ->onlyOnForms(),

            Number::make(
                self::translate('subscription.reseller-hosting-subscription.max-email-addresses'),
                'max_email_addresses'
            )
                ->nullable()
                ->hideFromIndex()
                ->onlyOnForms(),

            Number::make(
                self::translate('subscription.reseller-hosting-subscription.max-traffic'),
                'max_traffic'
            )
                ->nullable()
                ->hideFromIndex()
                ->onlyOnForms(),

            Number::make(
                self::translate('subscription.reseller-hosting-subscription.max-databases'),
                'max_databases'
            )
                ->nullable()
                ->hideFromIndex()
                ->onlyOnForms(),

            Text::make(
                self::translate('subscription.reseller-hosting-subscription.permissions'),
                'permissions'
            )
                ->nullable()
                ->showOnDetail($this->resource->permissions !== null),

            Text::make(
                self::translate('subscription.reseller-hosting-subscription.last-created-result'),
                'last_created_result'
            )
                ->nullable()
                ->onlyOnDetail(),

            DateTime::make(
                self::translate('subscription.reseller-hosting-subscription.last-created-result-received'),
                'last_created_result_received'
            )
                ->nullable()
                ->onlyOnDetail(),
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

    /**
     * @return array<int, Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            resolve(NovaFetchUserFromResellerSubscription::class),
        ];
    }
}
