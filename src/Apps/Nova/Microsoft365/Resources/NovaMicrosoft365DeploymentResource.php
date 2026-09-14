<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Microsoft365\Resources;

use Illuminate\Http\Request;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\Invoices\Filters\NovaMicrosoft365DeploymentKpnStatusFilter;
use Waterfront\Apps\Nova\Microsoft365\Actions\NovaMicrosoft365BulkStatusUpdateAction;
use Waterfront\Apps\Nova\Microsoft365\Actions\NovaMicrosoft365RetryOrderCreateAction;
use Waterfront\Apps\Nova\Microsoft365\Actions\NovaMicrosoft365RetryOrderModifyAction;
use Waterfront\Apps\Nova\Subscriptions\Resources\NovaSubscriptionResource;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365OrderStatus;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;

/** @property Microsoft365Deployment $resource */
class NovaMicrosoft365DeploymentResource extends Resource
{
    public static string $model = Microsoft365Deployment::class;

    public static $globallySearchable = false;

    /** @var array<mixed> */
    public static $with = ['microsoft365CustomerInfo', 'subscription', 'subscriptionChildren', 'microsoft365HttpLogs'];

    /** @var array<mixed> */
    public static $search = [
        'kpn_order_id',
    ];

    public static function getTranslationKey(): string
    {
        return 'microsoft365-subscriptions';
    }

    public function title(): string
    {
        return $this->resource->kpn_order_id !== null
            ? (string) $this->resource->kpn_order_id
            : (string) $this->resource->id;
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.microsoft365-subscriptions');
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make('ID', 'id')->hideFromIndex(),
            BelongsTo::make(
                self::translate('microsoft365-subscriptions.kpn-customer'),
                'microsoft365CustomerInfo',
                NovaMicrosoft365CustomerResource::class,
            )
                ->searchable()
                ->sortable(),
            Text::make(
                self::translate('microsoft365-subscriptions.parent-subscription'),
                'subscription_id',
            )->onlyOnForms(),
            Text::make(self::translate('subscription.relations.product'), fn (): string => $this->resource
                ->subscription
                ->children
                ->count() === 0
                    ? $this->resource->subscription->product->name
                    : $this->resource
                        ->subscription
                        ->children
                        ->firstOrFail()
                        ->product
                        ->name),
            Text::make(
                self::translate('subscription.attributes.period'),
                function (): string {
                    if ($this->resource->subscription->children->count() === 0) {
                        return $this->resource->subscription->contract_period === 1
                            ? $this->resource->subscription->contract_period
                            . ' '
                            . self::translate('subscription.attributes.month')
                            : $this->resource->subscription->contract_period
                            . ' '
                            . self::translate('subscription.attributes.period_name');
                    }

                    return $this->resource->subscription->children->firstOrFail()->contract_period === 1
                        ? $this->resource->subscription->children->firstOrFail()->contract_period
                        . ' '
                        . self::translate('subscription.attributes.month')
                        : $this->resource->subscription->children->firstOrFail()->contract_period
                        . ' '
                        . self::translate('subscription.attributes.period_name');
                },
            )->exceptOnForms(),
            Number::make('# Active seats', fn (): int => $this->resource
                ->subscription
                ->children
                ->where('administrative_status', AdministrativeStatus::ACTIVE->value)
                ->count()),
            Number::make('# Canceled seats', fn (): int => $this->resource
                ->subscription
                ->children
                ->where('administrative_status', AdministrativeStatus::CANCELED->value)
                ->count()),
            Number::make('KPN order ID', 'kpn_order_id')->sortable(),
            Select::make('KPN status', 'kpn_status')
                ->options([
                    [
                        'label' => self::translate('microsoft365-subscriptions.kpn-status.placed'),
                        'value' => Microsoft365OrderStatus::PLACED,
                    ],
                    [
                        'label' => self::translate('microsoft365-subscriptions.kpn-status.accepted'),
                        'value' => Microsoft365OrderStatus::ACCEPTED,
                    ],
                    [
                        'label' => self::translate('microsoft365-subscriptions.kpn-status.modify-pending'),
                        'value' => Microsoft365OrderStatus::MODIFY_PENDING,
                    ],
                    [
                        'label' => self::translate('microsoft365-subscriptions.kpn-status.modified'),
                        'value' => Microsoft365OrderStatus::MODIFIED,
                    ],
                    [
                        'label' => self::translate('microsoft365-subscriptions.kpn-status.active'),
                        'value' => Microsoft365OrderStatus::ACTIVE,
                    ],
                    [
                        'label' => self::translate('microsoft365-subscriptions.kpn-status.terminated'),
                        'value' => Microsoft365OrderStatus::TERMINATED,
                    ],
                ])
                ->displayUsingLabels()
                ->required()
                ->sortable(),
            DateTime::make('KPN start date', 'kpn_start_date')->onlyOnDetail(),
            HasMany::make(
                self::translate('subscription.singular'),
                'subscription',
                NovaSubscriptionResource::class,
            ),
            HasMany::make(
                self::translate('microsoft365-subscriptions.seats'),
                'subscriptionChildren',
                NovaSubscriptionResource::class,
            ),
            HasMany::make(
                self::translate('nova-resource-labels.microsoft365-sync-logs'),
                'microsoft365SyncLogs',
                NovaMicrosoft365SyncLogsResource::class,
            ),
            HasMany::make(
                self::translate('nova-resource-labels.microsoft365-logs'),
                'microsoft365HttpLogs',
                NovaMicrosoft365LogsResource::class,
            ),
        ];
    }

    /**
     * @return array<int, Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            resolve(NovaMicrosoft365RetryOrderCreateAction::class),
            resolve(NovaMicrosoft365RetryOrderModifyAction::class),
            resolve(NovaMicrosoft365BulkStatusUpdateAction::class),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    public function filters(Request $request): array
    {
        return [
            resolve(NovaMicrosoft365DeploymentKpnStatusFilter::class),
        ];
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return false;
    }
}
