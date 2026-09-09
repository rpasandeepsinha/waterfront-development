<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Resources;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Boolean as NovaBoolField;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\HasOne;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use Waterfront\Apps\Nova\Acronis\Resources\NovaBackupDeploymentResource;
use Waterfront\Apps\Nova\Customers\Resources\NovaCustomerResource;
use Waterfront\Apps\Nova\DNS\Actions\NovaAssignVanityNsAction;
use Waterfront\Apps\Nova\DNS\Actions\NovaResetDnsTemplateAction;
use Waterfront\Apps\Nova\DNS\Resources\NovaDnsDeploymentResource;
use Waterfront\Apps\Nova\DNS\Resources\NovaDnsLogResource;
use Waterfront\Apps\Nova\Domains\Actions\NovaChangeDomainProviderAction;
use Waterfront\Apps\Nova\Domains\Actions\NovaRetryDnsAction;
use Waterfront\Apps\Nova\Domains\Actions\NovaRetryDomainAction;
use Waterfront\Apps\Nova\Domains\Filters\SubscriptionDomainBusinessUnitFilter;
use Waterfront\Apps\Nova\Domains\Resources\NovaDomainSubscriptionResource;
use Waterfront\Apps\Nova\General\Filters\NovaDateEndFilter;
use Waterfront\Apps\Nova\General\Filters\NovaDateStartFilter;
use Waterfront\Apps\Nova\General\Resources\NovaNotesResource;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\General\Traits\ResolvesActionsAndFilters;
use Waterfront\Apps\Nova\General\Traits\UseClassNameForFilteringTrait;
use Waterfront\Apps\Nova\Hosting\Actions\NovaChangeHostingProviderAction;
use Waterfront\Apps\Nova\Hosting\Actions\NovaRetryHostingAction;
use Waterfront\Apps\Nova\Hosting\Resources\NovaHostingDeploymentResource;
use Waterfront\Apps\Nova\Hosting\Resources\NovaProvisioningHostingDeploymentResource;
use Waterfront\Apps\Nova\Hosting\Resources\NovaResellerHostingDeploymentResource;
use Waterfront\Apps\Nova\Hosting\Resources\NovaSitebuilderDeploymentResource;
use Waterfront\Apps\Nova\Microsoft365\Actions\NovaResendMicrosoft365TerminationsAction;
use Waterfront\Apps\Nova\Microsoft365\Resources\NovaMicrosoft365CustomerResource;
use Waterfront\Apps\Nova\Microsoft365\Resources\NovaMicrosoft365DeploymentResource;
use Waterfront\Apps\Nova\Microsoft365\Resources\NovaMicrosoft365LogsResource;
use Waterfront\Apps\Nova\Migrations\Resources\NovaMigratedSubscriptionResource;
use Waterfront\Apps\Nova\Products\Fields\NovaProductSelectField;
use Waterfront\Apps\Nova\Products\Resources\NovaProductResource;
use Waterfront\Apps\Nova\Redirects\Resources\NovaRedirectDeploymentResource;
use Waterfront\Apps\Nova\Ssl\Resources\NovaSslDeploymentResource;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaAddDomainToSpamExpertsAction;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaAddTrusteeAction;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaCallAdministrativelyExpireSubscriptions;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaCallTerminateSubscriptions;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaCancelAndCreditSubscriptionsAction;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaCreateOneTimeServiceAction;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaDowngradeAndCreditSubscriptionsAction;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaOpenSubscriptionInCompassAction;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaRemoveDnsZoneAction;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaResetDnsSecBulkAction;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaResumeExpiredSubscriptionsAction;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaRetrieveDnsZoneAction;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaRetrieveDnsZoneStandaloneAction;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaSetEndDateAndDetermineNextBillingDateAction;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaSiteBuilderSSOLoginAction;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaSuspendSubscriptionAction;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaUnsuspendSubscriptionAction;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaValidateDomainSubscriptionAction;
use Waterfront\Apps\Nova\Subscriptions\Fields\NovaSubscriptionAdministrativeStatusSelectField;
use Waterfront\Apps\Nova\Subscriptions\Fields\NovaSubscriptionTechnicalStatusSelectField;
use Waterfront\Apps\Nova\Subscriptions\Filters\NovaCategorySubscriptionsFilter;
use Waterfront\Apps\Nova\Subscriptions\Filters\NovaFailedSubscriptionsFilter;
use Waterfront\Apps\Nova\Subscriptions\Filters\NovaMigrationCustomerFilter;
use Waterfront\Apps\Nova\Subscriptions\Filters\NovaPendingSubscriptionsFilter;
use Waterfront\Apps\Nova\Subscriptions\Filters\NovaSubscriptionAdministrativeTypeFilter;
use Waterfront\Apps\Nova\Subscriptions\Filters\NovaSubscriptionMigrationGroupTypeFilter;
use Waterfront\Apps\Nova\Subscriptions\Filters\NovaSubscriptionProductFilter;
use Waterfront\Apps\Nova\Subscriptions\Filters\NovaSubscriptionProductGroupFilter;
use Waterfront\Apps\Nova\Subscriptions\Filters\NovaSubscriptionTechnicalStatusFilter;
use Waterfront\Apps\Nova\Subscriptions\Lenses\UncategorizedFailedSubscriptionsLens;
use Waterfront\Apps\Nova\VPS\Actions\NovaRetryVpsAction;
use Waterfront\Apps\Nova\VPS\Lenses\ActiveVpsSubscription;
use Waterfront\Apps\Nova\VPS\Resources\NovaVirtualMachineDeploymentResource;
use Waterfront\Domain\Customers\Repositories\CustomerRepository;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Pricing\Services\PricePersistService;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\RegistrationPriceRequest;
use Waterfront\Domain\Products\Enums\CustomPriceReasonType;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Transfers\Services\TransferService;
use Waterfront\Infra\Authentication\AuthorizationChecker;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\Translation\Translator;

/**
 * @property Subscription $resource
 *
 * @see NovaSubscriptionPolicy::attachAnyTransfer()
 * @see NovaSubscriptionPolicy::attachTransfer()
 * @see NovaSubscriptionPolicy::detachTransfer()
 */
class NovaSubscriptionResource extends Resource
{
    use UseClassNameForFilteringTrait;
    use ResolvesActionsAndFilters;

    public static string $model = Subscription::class;

    public static $perPageViaRelationship = 10;

    /**
     * @var array<mixed>
     */
    public static $search = [
        'uuid',
        'domain',
        'product.name',
        'product.productGroup.name',
    ];

    /**
     * @var array<mixed>
     */
    public static $with = [
        'product',
        'customer',
        'microsoft365Customer',
        'microsoft365Deployment',
        'notes',
        'product.productGroup',
        'hostingDeployment',
        'sslDeployment',
        'domainDeployment',
        'dnsDeployment',
        'migratedSubscriptions',
        'microsoft365Logs',
        'redirectDeployment',
    ];

    public static function getTranslationKey(): string
    {
        return 'subscription';
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.subscription');
    }

    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        if ($request->viaRelationship()) {
            return $query;
        }

        return $query
            ->where(
                fn (Builder $where) => $where->where(
                    function (Builder $priceQuery) {
                        $priceQuery->where('gross_price', '!=', 0);
                    }
                )
                    ->orWhereHas(
                        'product.productGroup',
                        fn (Builder $productGroupQuery): Builder => $productGroupQuery->where('slug', ProductGroupType::EXTENSION)
                    )
            );
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        $subscription = null;

        if (! $this->isResourceIndexRequest($request)) {
            $subscription = Subscription::where('id', $request->resourceId)->first();
        }

        /** @var TransferService $transferService */
        $transferService = resolve(TransferService::class);

        /** @var HostingService $hostingService */
        $hostingService = resolve(HostingService::class);

        return [
            ID::make()->hideFromIndex(),
            Text::make(self::translate('subscription.attributes.uuid'), 'uuid')->hideFromIndex(),
            BelongsTo::make(
                self::translate('subscription.relations.customer'),
                'customer',
                NovaCustomerResource::class
            )->sortable()->searchable(),
            BelongsTo::make(
                self::translate('microsoft365-customer.tenant-name'),
                'microsoft365Customer',
                NovaMicrosoft365CustomerResource::class
            )->canSee(fn (Request $request): bool => $this->canSeeSlugged(ProductGroupType::MICROSOFT_365, $subscription))->onlyOnDetail(),
            BelongsTo::make(
                self::translate('microsoft365-subscriptions.singular'),
                'microsoft365Deployment',
                NovaMicrosoft365DeploymentResource::class
            )->canSee(fn (Request $request): bool => $this->canSeeSlugged(ProductGroupType::MICROSOFT_365, $subscription))->onlyOnDetail(),
            Text::make(self::translate('subscription.attributes.domain'), 'domain')->copyable()->sortable(),
            Number::make(
                self::translate('subscription.attributes.contract_period_and_billing_period'),
                fn () => $this->resource->contract_period
                . ' - '
                . $this->resource->billing_period
            )->onlyOnDetail(),
            Text::make(self::translate('subscription.relations.product'), 'product')
                ->resolveUsing(fn (): string => $this->resource->product->name)
                ->hideWhenUpdating()
                ->hideWhenCreating()
                ->onlyOnIndex(),
            BelongsTo::make(
                self::translate('subscription.relations.product'),
                'product',
                NovaProductResource::class
            )
                ->hideWhenUpdating()
                ->hideWhenCreating()
                ->onlyOnDetail(),

            NovaSubscriptionAdministrativeStatusSelectField::makeForEditing()
                ->displayUsingLabels()
                ->required(),
            NovaSubscriptionAdministrativeStatusSelectField::makeForDisplay($this->resource->termination_date?->format(DateTimeFormat::DUTCH)),

            NovaSubscriptionTechnicalStatusSelectField::make()
                ->displayUsingLabels()
                ->help(self::translate('subscription.info.technical_status')),
            Text::make(
                self::translate('subscription.attributes.domain_status'),
                'domain_status',
            )
                ->resolveUsing(fn (): ?string => $this->resource->domainDeployment?->domain_status?->value)
                ->canSee(fn (Request $request): bool => $this->canSeeSlugged(ProductGroupType::EXTENSION, $subscription))
                ->onlyOnDetail(),
            Currency::make(self::translate('subscription.attributes.gross_price'), 'gross_price')
                ->required()
                ->rules('required')
                ->currency('EUR')
                ->step('0.01')
                ->asMinorUnits()
                ->hideFromIndex(),
            Currency::make(self::translate('subscription.attributes.net_price'), 'net_price')
                ->required()
                ->rules('required')
                ->currency('EUR')
                ->step('0.01')
                ->asMinorUnits()
                ->hideFromIndex(),
            Text::make(
                self::translate('subscription.attributes.start-end_date'),
                fn () => $this->resource->start_date->format(DateTimeFormat::DUTCHNOTIME)
                . ' - '
                . $this->resource->end_date->format(DateTimeFormat::DUTCHNOTIME)
            )->hideFromIndex()->hideWhenCreating()->hideWhenUpdating(),
            Date::make(self::translate('subscription.attributes.start_date'), 'start_date')
                ->required()
                ->hideFromDetail()
                ->displayUsing(fn () => $this->resource->start_date->format(DateTimeFormat::DUTCHNOTIME)),
            Date::make(self::translate('subscription.attributes.end_date'), 'end_date')
                ->hideFromDetail()
                ->displayUsing(fn () => $this->resource->end_date->format(DateTimeFormat::DUTCHNOTIME)),
            Text::make(
                self::translate('subscription.attributes.cancel-termination_date'),
                function () {
                    $cancel_date = $this->resource->cancel_date === null ? '' : $this->resource->cancel_date->format(DateTimeFormat::DUTCH);
                    $termination_date = $this->resource->termination_date === null ? '' : $this->resource->termination_date->format(DateTimeFormat::DUTCH);
                    return $cancel_date . ' - ' . $termination_date;
                }
            )->hideFromDetail(fn () => $this->resource->termination_date === null && $this->resource->cancel_date === null)
                ->hideFromIndex()->hideWhenCreating()->hideWhenUpdating(),
            Text::make(
                self::translate('nova-action.cancel_subscriptions.reason'),
                fn () => self::translate('cancel_subscriptions.reason.' . strtolower($this->resource->cancel_reason->value ?? '')),
            )->canSee(fn (): bool => $this->resource->cancel_reason !== null)->onlyOnDetail(),
            Date::make(self::translate('subscription.attributes.suspended_date'), 'suspended_at')
                ->nullable()
                ->hideFromIndex()
                ->hideFromDetail(fn () => $this->resource->suspended_at === null)
                ->displayUsing(fn () => $this->resource->suspended_at?->format(DateTimeFormat::DUTCHNOTIME))
                ->hideWhenCreating()
                ->hideWhenUpdating(),
            Date::make(self::translate('subscription.attributes.next_billing_date'), 'next_billing_date')
                ->displayUsing(fn () => $this->resource->next_billing_date->format(DateTimeFormat::DUTCHNOTIME))
                ->hideFromIndex()
                ->hideWhenCreating()
                ->hideWhenUpdating(),
            NovaBoolField::make(self::translate('nova-resource-labels.subscription.relation.note'), 'notes')
                ->hideWhenCreating()->hideWhenUpdating()->hideFromDetail(fn () => $this->resource->notes()->count() === 0),
            NovaBoolField::make(self::translate('transfer.relations.is_in_transfer'), 'in_transfer')
                ->onlyOnDetail()->hideFromDetail(fn () => $transferService->hasOpenTransfer($this->resource) === false),

            Text::make(
                self::translate('order.singular'),
                'id',
                function (): ?string {
                    $item = $this->resource->orderLineItem;
                    /** @var OrderLineItem|null $item */
                    $order = $item?->order;
                    /** @var Order|null $order */
                    if ($order instanceof Order) {
                        $linkText = sprintf(
                            '%s %s %s',
                            $order->id,
                            $order->payment_method->value,
                            $order->created_at?->format(DateTimeFormat::DUTCH)
                        );

                        return "<a href='" . sprintf('/nova/resources/nova-order-resources/%s', $order->id) . "' class='no-underline dim text-primary font-bold'>" . $linkText . '</a>';
                    }

                    return null;
                }
            )->onlyOnDetail()->asHtml(),

            /** @see Subscription::hostingDeployment() */
            HasOne::make(
                self::translate('hosting-subscription.singular'),
                'hostingDeployment',
                NovaHostingDeploymentResource::class
            )->canSee(fn (Request $request): bool => $this->canSeeSlugged(ProductGroupType::HOSTING, $subscription)),

            /** @see Subscription::cloudStackVirtualMachineDeployment() */
            HasOne::make(
                self::translate('virtual_machine_deployment.singular'),
                'cloudStackVirtualMachineDeployment',
                NovaVirtualMachineDeploymentResource::class
            )->canSee(fn (Request $request): bool => $this->canSeeSlugged(ProductGroupType::VPS, $subscription)),

            /** @see Subscription::resellerHostingDeployment() */
            HasOne::make(
                self::translate('hosting-subscription.singular'),
                'resellerHostingDeployment',
                NovaResellerHostingDeploymentResource::class
            )->canSee(fn (Request $request): bool => $this->canSeeSlugged(ProductGroupType::RESELLER_HOSTING, $subscription)),

            /** @see Subscription::redirectDeployment() */
            HasOne::make(
                self::translate('redirect-deployment.singular'),
                'redirectDeployment',
                NovaRedirectDeploymentResource::class
            )->canSee(fn (Request $request): bool => $this->canSeeSlugged(ProductGroupType::REDIRECT, $subscription)),

            /** @see Subscription::provisionHostingDeployment() */
            HasOne::make(
                self::translate('hosting-deployment.singular'),
                'provisionHostingDeployment',
                NovaProvisioningHostingDeploymentResource::class
            )->canSee(fn (Request $request): bool => $this->canSeeSlugged(ProductGroupType::HOSTING, $subscription)),

            /** @see Subscription::provisionSitebuilderDeployment() */
            HasOne::make(
                self::translate('sitebuilder-deployment.singular'),
                'provisionSitebuilderDeployment',
                NovaSitebuilderDeploymentResource::class
            )->canSee(fn (Request $request): bool => $subscription?->product->isSitebuilderProduct() ?? false),

            /** @see Subscription::$provisionBackupDeployment */
            HasOne::make(
                self::translate('backup-deployment.singular'),
                'provisionBackupDeployment',
                NovaBackupDeploymentResource::class
            )->canSee(fn (Request $request): bool => $subscription?->product->isBackupProduct() ?? false),

            /** @see Subscription::$sslDeployment */
            HasOne::make(
                self::translate('ssl-subscription.singular'),
                'sslDeployment',
                NovaSslDeploymentResource::class
            )->canSee(fn (Request $request): bool => $this->canSeeSlugged(ProductGroupType::SSL, $subscription)),

            /** @see Subscription::$domainDeployment */
            HasOne::make(
                self::translate('domain-subscription.singular'),
                'domainDeployment',
                NovaDomainSubscriptionResource::class
            )->canSee(fn (Request $request): bool => $this->canSeeSlugged(ProductGroupType::EXTENSION, $subscription)),

            /** @see Subscription::$dnsDeployment */
            HasOne::make(
                self::translate('dns-deployment.singular'),
                'dnsDeployment',
                NovaDnsDeploymentResource::class
            )->canSee(fn (Request $request): bool => $this->canSeeSlugged(ProductGroupType::DNS, $subscription)),

            /** @see Subscription::notes() */
            HasMany::make(
                self::translate('nova-resource-labels.subscription.relation.note'),
                'notes',
                NovaNotesResource::class
            ),

            /**
             * @see Subscription::subscriptionChanges()
             */
            HasMany::make(
                self::translate('subscription-changes.singular'),
                'subscriptionChanges',
                NovaSubscriptionChangeResource::class
            ),

            /** @see Subscription::dnsLogs() */
            HasMany::make(
                self::translate('dns-logs.plural'),
                'dnsLogs',
                NovaDnsLogResource::class
            )->canSee(fn (Request $request): bool => $this->canSeeSlugged(ProductGroupType::DNS, $subscription)),

            Text::make(self::translate('subscription.attributes.domainprovider'), 'domainDeployment.provider.slug')
                ->canSee(fn (Request $request): bool => $this->canSeeSlugged(ProductGroupType::EXTENSION, $subscription))
                ->nullable()
                ->onlyOnDetail(),

            Text::make(self::translate('subscription.attributes.hostingprovider'), 'hostingproviderslug')
                ->resolveUsing(fn (): string => $hostingService->getProviderSlug($this->resource) !== null
                    ? $this->resolveProviderContext($hostingService->getProviderSlug($this->resource))
                    : '---')
                ->canSee(fn (Request $request): bool => $this->canSeeSlugged(ProductGroupType::HOSTING, $subscription))
                ->nullable()
                ->hideWhenCreating()
                ->hideWhenUpdating()
                ->onlyOnDetail(),

            Text::make(self::translate('subscription.attributes.sslprovider'), 'ssl_provider_slug')
                ->resolveUsing(fn (): string => $this->resource->sslDeployment?->provider->slug->value ?? 'unknown')
                ->canSee(fn (Request $request): bool => $this->canSeeSlugged(ProductGroupType::SSL, $subscription))
                ->nullable()
                ->onlyOnDetail(),

            /** @see Subscription::transfers() */
            BelongsToMany::make(
                self::translate('transfer.plural'),
                'transfers',
                NovaTransferResource::class
            ),
            Text::make(
                self::translate('subscriptions.subscriptions-categories'),
                'category',
            )->displayUsing(fn () => $this->resource->category?->name->value ?? 'N/A')->canSee(fn (Request $request): bool => $this->resource->category !== null)->hideFromIndex(),

            /** @see Subscription::migratedSubscriptions() */
            HasMany::make(
                self::translate('nova-resource-labels.migration'),
                'migratedSubscriptions',
                NovaMigratedSubscriptionResource::class
            ),

            /** @see Subscription::parent() */
            HasMany::make(
                self::translate('subscription.relations.parent'),
                'parent',
                self::class
            )->onlyOnDetail()->canSee(fn (Request $request): bool => $subscription?->parent !== null),

            /** @see Subscription::children() */
            HasMany::make(
                self::translate('subscription.relations.child'),
                'children',
                self::class
            )->onlyOnDetail()->canSee(fn (Request $request): bool => $subscription?->children->count() > 0),

            Date::make(self::translate('nova-resource-labels.created-at'), 'created_at')
                ->hideFromDetail(fn () => $this->resource->created_at === null)
                ->displayUsing(fn () => $this->resource->created_at?->format(DateTimeFormat::DUTCHNOTIME))
                ->onlyOnDetail(),

            /** @see Subscription::microsoft365Logs() */
            HasMany::make(
                self::translate('microsoft365-logs.plural'),
                'microsoft365Logs',
                NovaMicrosoft365LogsResource::class
            )->canSee(fn (Request $request): bool => $this->canSeeSlugged(ProductGroupType::MICROSOFT_365, $subscription))->onlyOnDetail(),
        ];
    }

    public function subtitle(): string
    {
        return "Product: {$this->resource->product->name}";
    }

    /**
     * @return array<int, Action>
     */
    public function actions(NovaRequest $request): array
    {
        // It's never a good idea to execute an action for ALL subscriptions
        if ($request->allResourcesSelected()) {
            return [];
        }

        return [
            // General actions
            $this->resolveAction(NovaSetEndDateAndDetermineNextBillingDateAction::class),
            $this->resolveAction(NovaSuspendSubscriptionAction::class),
            $this->resolveAction(NovaUnsuspendSubscriptionAction::class),
            $this->resolveAction(NovaCancelAndCreditSubscriptionsAction::class),
            $this->resolveAction(NovaDowngradeAndCreditSubscriptionsAction::class),
            $this->resolveAction(NovaAddDomainToSpamExpertsAction::class),
            $this->resolveAction(NovaCreateOneTimeServiceAction::class),
            $this->resolveAction(NovaResumeExpiredSubscriptionsAction::class),
            $this->resolveAction(NovaOpenSubscriptionInCompassAction::class),
            $this->resolveAction(NovaAddTrusteeAction::class),
            $this->resolveAction(NovaCallAdministrativelyExpireSubscriptions::class)->canSee(fn () => $this->canPerformRestrictedSubscriptionLifecycleActions()),
            $this->resolveAction(NovaCallTerminateSubscriptions::class)->canSee(fn () => $this->canPerformRestrictedSubscriptionLifecycleActions()),

            // Hosting actions
            $this->resolveAction(NovaChangeHostingProviderAction::class),
            $this->resolveAction(NovaRetryHostingAction::class),

            // Domain actions
            $this->resolveAction(NovaChangeDomainProviderAction::class),
            $this->resolveAction(NovaRetryDomainAction::class),
            $this->resolveAction(NovaValidateDomainSubscriptionAction::class),

            // DNS actions
            $this->resolveAction(NovaRetryDnsAction::class),
            $this->resolveAction(NovaAssignVanityNsAction::class),
            $this->resolveAction(NovaResetDnsTemplateAction::class),
            $this->resolveAction(NovaRetrieveDnsZoneAction::class),
            $this->resolveAction(NovaRetrieveDnsZoneStandaloneAction::class),
            $this->resolveAction(NovaRemoveDnsZoneAction::class),
            $this->resolveAction(NovaResetDnsSecBulkAction::class)->standalone(),

            // M365 actions
            $this->resolveAction(NovaResendMicrosoft365TerminationsAction::class),

            // SSO Actions
            $this->resolveAction(NovaSiteBuilderSSOLoginAction::class),

            // VPS Actions
            $this->resolveAction(NovaRetryVpsAction::class),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [
            $this->resolveFilter(NovaSubscriptionAdministrativeTypeFilter::class),
            $this->resolveFilter(NovaSubscriptionTechnicalStatusFilter::class),
            $this->resolveFilter(NovaSubscriptionProductFilter::class),
            $this->resolveFilter(NovaSubscriptionProductGroupFilter::class),
            $this->resolveFilter(NovaCategorySubscriptionsFilter::class),
            $this->resolveFilter(NovaDateStartFilter::class),
            $this->resolveFilter(NovaDateEndFilter::class),
            $this->resolveFilter(SubscriptionDomainBusinessUnitFilter::class),
            $this->resolveFilter(NovaSubscriptionMigrationGroupTypeFilter::class),
            $this->resolveFilter(NovaMigrationCustomerFilter::class),
            $this->resolveFilter(NovaPendingSubscriptionsFilter::class),
            $this->resolveFilter(NovaFailedSubscriptionsFilter::class),
        ];
    }

    /**
     * @return array<int, Field>
     */
    public function fieldsForUpdate(NovaRequest $request): array
    {
        return [
            BelongsTo::make(
                self::translate('subscription.relations.customer'),
                'customer',
                NovaCustomerResource::class
            )->searchable()->relatableQueryUsing(fn (NovaRequest $request, Builder $query): Builder => $query->withoutEagerLoads()),
            Text::make(self::translate('subscription.attributes.domain'), 'domain'),
            NovaProductSelectField::make('product_uuid')->required(),
            NovaSubscriptionAdministrativeStatusSelectField::makeForEditing()
                ->displayUsingLabels()
                ->required(),
            NovaSubscriptionTechnicalStatusSelectField::make()
                ->displayUsingLabels()
                ->help(self::translate('subscription.info.technical_status')),

            Number::make(self::translate('subscription.attributes.contract_period'), 'contract_period')
                ->readonly()
                ->help(self::translate('subscription.info.update_periods')),
            Number::make(self::translate('subscription.attributes.billing_period'), 'billing_period')
                ->readonly()
                ->help(self::translate('subscription.info.update_periods')),
            Currency::make(self::translate('subscription.attributes.gross_price'), 'gross_price')
                ->required()
                ->rules('required')
                ->currency('EUR')
                ->step('0.01')
                ->asMinorUnits(),
            Currency::make(self::translate('subscription.attributes.net_price'), 'net_price')
                ->required()
                ->rules('required')
                ->currency('EUR')
                ->step('0.01')
                ->asMinorUnits(),
            Date::make(self::translate('subscription.attributes.start_date'), 'start_date')
                ->displayUsing(fn () => $this->resource->start_date->format(DateTimeFormat::DUTCHNOTIME))
                ->readonly()
                ->help(self::translate('subscription.info.update_periods')),
            Date::make(self::translate('subscription.attributes.end_date'), 'end_date')
                ->displayUsing(fn () => $this->resource->end_date->format(DateTimeFormat::DUTCHNOTIME))
                ->readonly()
                ->help(self::translate('subscription.info.update_periods')),
        ];
    }

    public function title(): string
    {
        $archivedString = '';

        if ($this->resource->administrative_status === AdministrativeStatus::ARCHIVED->value) {
            $archivedString = ' (' . self::translate('subscription.administrative_statuses.archived') . ')';
        }

        if ($this->resource->domain === null || strlen($this->resource->domain) < 1) {
            return $this->resource->id . $archivedString;
        }

        return $this->resource->domain . $archivedString;
    }

    /**
     * @return array<int, Lens>
     */
    public function lenses(NovaRequest $request): array
    {
        return [
            new ActiveVpsSubscription(),
            new UncategorizedFailedSubscriptionsLens(),
        ];
    }

    protected static function afterValidation(NovaRequest $request, Validator $validator): void
    {
        $productRepository = resolve(ProductRepository::class);
        $customerRepository = resolve(CustomerRepository::class);
        $priceResolver = resolve(PriceResolver::class);
        $pricePersistService = resolve(PricePersistService::class);
        $translator = resolve(Translator::class);

        assert(is_numeric($request->input('contract_period')));
        assert(is_numeric($request->input('billing_period')));
        $contractPeriod = (int) $request->input('contract_period');
        $billingPeriod = (int) $request->input('billing_period');

        $productUuid = $request->input('product_uuid');
        assert(is_string($productUuid));
        $product = $productRepository->findProductByUuid($productUuid);

        $customer = $customerRepository->getById($request->integer('customer'));
        $priceRequest = new PriceRequest([new RegistrationPriceRequest($product)], $customer);
        $priceList = $priceResolver->getPriceList($priceRequest);

        try {
            $priceList->getProductPrice($product->slug, $contractPeriod, $billingPeriod);
        } catch (ItemNotFoundException) {
            throw ValidationException::withMessages([
                'billing_period' => $translator->translate('nova-validation.error.product_price_does_not_exist'),
                'contract_period' => $translator->translate('nova-validation.error.product_price_does_not_exist'),
            ]);
        }

        $subscription = Subscription::where('id', (int) $request->resourceId)->firstOrFail();

        $newPrice = (int) ($request->float('net_price') * 100);
        assert($newPrice >= 0);
        if ($subscription->net_price !== $newPrice) {
            $pricePersistService->persistCustomPrice($subscription, $newPrice, true, CustomPriceReasonType::MANUAL_NOVA_OVERRIDE);
        }
    }

    private function resolveProviderContext(string $slug): string
    {
        if ($slug === 'integratedservice') {
            $theme = resolve(ConfigurationInterface::class)->getAsString('app.theme');
            $slug .= ' ' . $theme;
        }

        return $slug;
    }

    private function canSeeSlugged(ProductGroupType $productType, Subscription|null $subscription): bool
    {
        return $subscription === null
            || $subscription->product->productGroup->slug === $productType
            || $subscription->{$productType->value . 'Subscription'};
    }

    private function canPerformRestrictedSubscriptionLifecycleActions(): bool
    {
        /** @var AuthorizationChecker $authorizationChecker */
        $authorizationChecker = App::make(AuthorizationChecker::class);

        return $authorizationChecker->can(Permissions::PERFORM_RESTRICTED_SUBSCRIPTION_LIFECYCLE_ACTIONS);
    }
}
