<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Migrations\Resources;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Panel;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use Waterfront\Apps\Nova\Customers\Resources\NovaCustomerResource;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\General\Traits\ResolvesActionsAndFilters;
use Waterfront\Apps\Nova\Migrations\Actions\NovaMigratedCustomerReplaceReferenceAction;
use Waterfront\Apps\Nova\Migrations\Filters\NovaMigratedStateStatusFilter;
use Waterfront\Apps\Nova\Migrations\Filters\NovaMigrationStateBatchGroupFilter;
use Waterfront\Apps\Nova\Migrations\Filters\NovaMigrationStateProductFilter;
use Waterfront\Apps\Nova\Migrations\Filters\NovaMigrationStateProductGroupFilter;
use Waterfront\Apps\Nova\Migrations\Filters\NovaMigrationStateReferenceNameFilter;
use Waterfront\Apps\Nova\Migrations\Lenses\NovaMigratedTotalLens;
use Waterfront\Apps\Nova\Products\Resources\NovaProductResource;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Authentication\AuthorizationChecker;

/**
 * TODO Re-add the export CSV action with the correct fields.
 *
 * @see https://yh-jira.atlassian.net/browse/SWD-10027
 *
 * @property Subscription $resource
 */
class NovaMigrationStateResource extends Resource
{
    use ResolvesActionsAndFilters;

    public static string $model = Subscription::class;

    public static $globallySearchable = false;

    /** @var array<mixed> */
    public static $search = [
        'domain',
        'customer.email',
        'customer.customer_number',
        'customer.migratedCustomers.reference_customer_number',
        'migratedSubscriptions.reference_subscription_id',
        'customer.migratedCustomers.group_type',
    ];

    /** @var array<mixed> */
    public static $with = [
        'customer.migratedCustomers',
        'migratedSubscriptions',
        'product.productGroup',
        'domainDeployment.provider',
        'hostingDeployment.provider',
        'hostingDeployment.mailProvider',
        'hostingDeployment.sitebuilderProvider',
        'sslDeployment.provider',
    ];

    public static function getTranslationKey(): string
    {
        return 'migration_state';
    }

    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query->has('migratedSubscriptions');
    }

    /**
     * @return array<Field|Panel>
     */
    public function fields(NovaRequest $request)
    {
        return [
            Panel::make('Migration Data', [
                Text::make(
                    self::translate('migration_state.external_customer_id'),
                    'customer.migratedCustomers.0.reference_customer_number',
                )->sortable(),
                Text::make(
                    self::translate('customer.attributes.reference_subscription_id'),
                    'migratedSubscriptions.0.reference_subscription_id',
                )->sortable(),
                Text::make(
                    self::translate('migrated.subscription.reference_name'),
                    'customer.migratedCustomers.0.reference_name',
                )->sortable(),
                Text::make(
                    self::translate('migrated.subscription.group_type'),
                    'customer.migratedCustomers.0.group_type',
                )->sortable(),
            ]),

            Panel::make('Customer', [
                BelongsTo::make(
                    self::translate('subscription.relations.customer'),
                    'customer',
                    NovaCustomerResource::class,
                )->sortable(),
                Text::make(self::translate('migration_state.waterfront_customer_id'), 'customer.id')->hideFromIndex(),
                Text::make(
                    self::translate('migration_state.waterfront_customer_number'),
                    'customer.customer_number',
                )->hideFromIndex(),
                Text::make(self::translate('customer.attributes.email'), 'customer.email')->hideFromIndex(),
                Text::make(self::translate('customer.attributes.first_name'), 'customer.first_name')->hideFromIndex(),
                Text::make(self::translate('customer.attributes.last_name'), 'customer.last_name')->hideFromIndex(),
                Text::make(
                    self::translate('customer.attributes.organization'),
                    'customer.organization',
                )->hideFromIndex(),
                Boolean::make(
                    self::translate('migrated.customer.administrative_successful'),
                    'customer.migratedCustomers.0.administrative_successful',
                )->hideFromIndex(),
                Boolean::make(
                    self::translate('migrated.customer.enable_invoicing'),
                    'customer.migratedCustomers.0.enable_invoicing',
                )->hideFromIndex(),
                Boolean::make(
                    self::translate('migrated.customer.successful'),
                    'customer.migratedCustomers.0.successful',
                )->hideFromIndex(),
                DateTime::make(
                    self::translate('migrated.customer.migration_date'),
                    'customer.migratedCustomers.0.migrated_at',
                )->hideFromIndex(),
            ]),

            Panel::make('Contact Information', [
                Text::make(
                    self::translate('domain-contact.attributes.country_code'),
                    'customer.address.country_code',
                )->hideFromIndex(),
                Text::make(
                    self::translate('domain-contact.attributes.phone_country_code'),
                    'customer.phone_country_code',
                )->hideFromIndex(),
                Text::make(
                    self::translate('domain-contact.attributes.phone_area_code'),
                    'customer.phone_area_code',
                )->hideFromIndex(),
                Text::make(
                    self::translate('domain-contact.attributes.phone_subscriber_number'),
                    'customer.phone_subscriber_number',
                )->hideFromIndex(),
            ]),

            Panel::make('Financials', [
                Text::make(self::translate('customer.attributes.coc_number'), 'coc_number')->hideFromIndex(),
                Text::make(self::translate('customer.attributes.vat_number'), 'vat_number')->hideFromIndex(),
                Text::make(self::translate('customer.attributes.vat_rate'), 'vat_rate')->hideFromIndex(),

                Text::make(self::translate('migration_state.wallet_id'), 'customer.wallet.id')->hideFromIndex(),
                Text::make(self::translate('customer.wallet.email.amount'), 'customer.wallet.amount')->hideFromIndex(),
                Text::make(
                    self::translate('nova-filter.customer.wallet.refund_requested_at'),
                    'customer.wallet.refund_requested_at',
                )->hideFromIndex(),
            ]),

            Panel::make('Subscription', [
                Text::make(
                    self::translate('subscription.attributes.domain'),
                    fn () => sprintf(
                        "<a class='link-default' href='/nova/resources/nova-subscription-resources/%s'>%s</a>",
                        $this->resource->id,
                        $this->resource->domain ?? $this->resource->id,
                    ),
                )->asHtml(),
                BelongsTo::make(
                    self::translate('order_line_item.attributes.product_name'),
                    'product',
                    NovaProductResource::class,
                )->sortable(),
                Text::make(self::translate('migration_state.provider'))
                    ->sortable()
                    ->displayUsing(
                        fn () => (
                            $this->resource->domainDeployment?->provider->slug->value ?? $this->resource->hostingDeployment?->provider?->slug->value ?? $this->resource->hostingDeployment?->mailProvider?->slug->value ?? $this->resource->hostingDeployment?->sitebuilderProvider?->slug->value ?? $this->resource->sslDeployment?->provider->slug->value
                            ?? null
                        ),
                    ),
                Text::make(self::translate('nova-action.technical_status'), 'technical_status')->sortable(),
                Text::make(
                    self::translate('subscription.relations.product_group'),
                    'product.productGroup.slug',
                )->hideFromIndex(),
                Text::make(self::translate('subscription.attributes.gross_price'), 'gross_price')->hideFromIndex(),
                Text::make(self::translate('subscription.attributes.net_price'), 'net_price')->hideFromIndex(),
                Text::make(
                    self::translate('nova-action.administrative_status'),
                    'administrative_status',
                )->hideFromIndex(),
                DateTime::make(
                    self::translate('migration_state.subscription_last_updated'),
                    'updated_at',
                )->hideFromIndex(),
                Date::make(self::translate('migration_state.subscription_renewal_date'), 'end_date')->hideFromIndex(),
                Date::make(self::translate('migration_state.subscription_cancel_date'), 'cancel_date')->hideFromIndex(),
                Date::make(
                    self::translate('migration_state.subscription_next_billing_date'),
                    'next_billing_date',
                )->hideFromIndex(),
            ]),

            Panel::make('Technical Details', [
                Text::make(self::translate('server.attributes.hostname'), 'server_hostname')
                    ->displayUsing(
                        function () {
                            $hostingDeployment = $this->resource->hostingDeployment;

                            $provider =
                                $hostingDeployment->provider ?? $hostingDeployment->mailProvider
                                    ?? $hostingDeployment?->sitebuilderProvider;

                            return match ($provider?->type) {
                                ProviderType::HOSTING => $hostingDeployment?->server?->hostname,
                                ProviderType::MAILONLY => $hostingDeployment?->mailOnlyServer?->hostname,
                                ProviderType::SITEBUILDER => $hostingDeployment?->basekitServer?->hostname,
                                default => null,
                            };
                        },
                    )
                    ->hideFromIndex(),
                Text::make(
                    self::translate('domain-contact.attributes.external_handle'),
                    'domainDeployment.contactOwner.pivot.external_contact',
                )
                    ->displayUsing(
                        function () {
                            $contactOwner = $this->resource->domainDeployment?->contactOwner;

                            if ($contactOwner === null) {
                                return null;
                            }

                            return $contactOwner->providers->first()?->pivot?->external_contact;
                        },
                    )
                    ->hideFromIndex(),
            ]),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [
            $this->resolveFilter(NovaMigrationStateReferenceNameFilter::class),
            $this->resolveFilter(NovaMigrationStateBatchGroupFilter::class),
            $this->resolveFilter(NovaMigrationStateProductGroupFilter::class),
            $this->resolveFilter(NovaMigrationStateProductFilter::class),
            $this->resolveFilter(NovaMigratedStateStatusFilter::class),
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

    /**
     * @return array<int, Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            $this->resolveAction(NovaMigratedCustomerReplaceReferenceAction::class)
                ->canSee(fn () => $this->canManageMigrations())
                ->standalone(),
        ];
    }

    /**
     * @return array<int, Lens>
     */
    public function lenses(NovaRequest $request): array
    {
        return [
            new NovaMigratedTotalLens(),
        ];
    }

    private function canManageMigrations(): bool
    {
        /** @var AuthorizationChecker $authorizationChecker */
        $authorizationChecker = App::make(AuthorizationChecker::class);

        return $authorizationChecker->can(Permissions::MANAGE_MIGRATIONS);
    }
}
