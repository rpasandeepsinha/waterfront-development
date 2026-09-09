<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Hosting\Resources;

use Illuminate\Http\Request;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\Hosting\Actions\NovaFetchEmailForwardsForDomain;
use Waterfront\Apps\Nova\Hosting\Actions\NovaFetchUserFromSubscription;
use Waterfront\Apps\Nova\Hosting\Actions\NovaGenerateSsoAction;
use Waterfront\Apps\Nova\Hosting\Actions\NovaRetryWordpressInstallationIdJobAction;
use Waterfront\Apps\Nova\Subscriptions\Resources\NovaSubscriptionResource;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;

/** @property HostingDeployment $resource */
class NovaHostingDeploymentResource extends Resource
{
    public static string $model = HostingDeployment::class;

    public static $displayInNavigation = false;

    public static $globallySearchable = false;

    /** @var array<mixed> */
    public static $search = [
        'id',
    ];

    public static function getTranslationKey(): string
    {
        return 'hosting-subscription';
    }

    /** @return array<int, Field> */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make(
                self::translate('subscription.hosting-subscription.internal_subscription'),
                'subscription',
                NovaSubscriptionResource::class
            )->sortable()
                ->exceptOnForms(),

            Text::make(
                self::translate('subscription.hosting-subscription.plesk_username'),
                'plesk_customer_username'
            )->nullable()
                ->showOnDetail($this->resource->plesk_customer_username !== null),

            Number::make(
                self::translate('subscription.hosting-subscription.customer_id'),
                'plesk_customer_id'
            )->hideFromIndex()->nullable()
                ->canSee(
                    fn (): bool => ($request->isCreateOrAttachRequest() || $request->isUpdateOrUpdateAttachedRequest())
                        || $this->resource->plesk_customer_id !== null
                ),

            Text::make(
                self::translate('subscription.hosting-subscription.directadmin_username'),
                'directadmin_customer_username'
            )->nullable()
                ->showOnDetail($this->resource->directadmin_customer_username !== null),

            BelongsTo::make(
                self::translate('subscription.hosting-subscription.server'),
                'server',
                NovaServerResource::class
            )->sortable()->nullable()
                ->canSee(
                    fn (): bool =>
                    ($request->isCreateOrAttachRequest() || $request->isUpdateOrUpdateAttachedRequest())
                    || $this->resource->server !== null
                ),

            Select::make(
                self::translate('subscription.hosting-subscription.provider'),
                'provider_id',
            )->options(function (): array {
                $groups = Provider::where('type', ProviderType::HOSTING)->get(['id', 'slug']);
                return array_column($groups->toArray(), 'slug', 'id');
            })->displayUsingLabels()
                ->sortable()
                ->nullable()
                ->canSee(
                    fn (): bool =>
                    ($request->isCreateOrAttachRequest() || $request->isUpdateOrUpdateAttachedRequest())
                    || $this->resource->provider !== null
                ),

            BelongsTo::make(
                self::translate('subscription.hosting-subscription.mail-only-server'),
                'mailOnlyServer',
                NovaServerResource::class,
            )->sortable()->nullable()
                ->canSee(
                    fn (): bool =>
                    ($request->isCreateOrAttachRequest() || $request->isUpdateOrUpdateAttachedRequest())
                    || $this->resource->mailOnlyServer !== null
                ),

            Select::make(
                self::translate('subscription.hosting-subscription.mail-only-provider'),
                'mail_only_provider_id',
            )->options(function (): array {
                $groups = Provider::query()->where('type', '=', ProviderType::MAILONLY)->get(['id', 'slug']);
                return array_column($groups->toArray(), 'slug', 'id');
            })->displayUsingLabels()
                ->sortable()
                ->nullable()
                ->canSee(
                    fn (): bool =>
                        ($request->isCreateOrAttachRequest() || $request->isUpdateOrUpdateAttachedRequest())
                        || $this->resource->mailProvider !== null
                ),

            Number::make(
                self::translate('subscription.hosting-subscription.wp_installation_id'),
                'wp_installation_id'
            )->onlyOnDetail()
                ->showOnDetail($this->resource->wp_installation_id !== null),

            Number::make(
                self::translate('subscription.hosting-subscription.basekit_user_ref'),
                'basekit_user_ref'
            )->nullable()
                ->canSee(
                    fn (): bool =>
                        ($request->isCreateOrAttachRequest() || $request->isUpdateOrUpdateAttachedRequest())
                        || $this->resource->subscription->product->isSitebuilderProduct()
                ),

            Number::make(
                self::translate('subscription.hosting-subscription.basekit_site_ref'),
                'basekit_site_ref'
            )->nullable()
                ->canSee(
                    fn (): bool =>
                        ($request->isCreateOrAttachRequest() || $request->isUpdateOrUpdateAttachedRequest())
                        || $this->resource->subscription->product->isSitebuilderProduct()
                ),

            BelongsTo::make(
                self::translate('subscription.hosting-subscription.sitebuilder-server'),
                'basekitServer',
                NovaServerResource::class
            )->sortable()->nullable()
                ->canSee(
                    fn (): bool =>
                        ($request->isCreateOrAttachRequest() || $request->isUpdateOrUpdateAttachedRequest())
                        || $this->resource->subscription->product->isSitebuilderProduct()
                ),

            Select::make(
                self::translate('subscription.hosting-subscription.sitebuilder-provider'),
                'sitebuilder_provider_id',
            )->options(function (): array {
                $groups = Provider::where('type', ProviderType::SITEBUILDER)->get(['id', 'slug']);
                return array_column($groups->toArray(), 'slug', 'id');
            })->displayUsingLabels()
                ->sortable()
                ->nullable()
                ->canSee(
                    fn (): bool =>
                        ($request->isCreateOrAttachRequest() || $request->isUpdateOrUpdateAttachedRequest())
                        || $this->resource->sitebuilderProvider !== null
                ),

            BelongsTo::make(
                self::translate('subscription.hosting-subscription.spamexperts-cluster'),
                'spamexpertsCluster',
                NovaSpamExpertsClusterResource::class
            )->help(self::translate('subscription.hosting-subscription.spamexperts-cluster.help'))
                ->nullable()
                ->showOnDetail($this->resource->spamExpertsCluster !== null),
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

    /** @return array<int, Action> */
    public function actions(NovaRequest $request): array
    {
        return [
            resolve(NovaFetchUserFromSubscription::class),
            resolve(NovaFetchEmailForwardsForDomain::class),
            resolve(NovaGenerateSsoAction::class),
            resolve(NovaRetryWordpressInstallationIdJobAction::class),
        ];
    }
}
