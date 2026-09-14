<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Microsoft365\Resources;

use Illuminate\Http\Request;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\Customers\Resources\NovaCustomerResource;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\Invoices\Filters\NovaMicrosoft365CustomerInfoTechnicalStatusFilter;
use Waterfront\Apps\Nova\Microsoft365\Actions\NovaMicrosoft365RetryCreateKpnCustomerAction;
use Waterfront\Apps\Nova\Microsoft365\Rules\KpnCustomerId;
use Waterfront\Apps\Nova\Microsoft365\Rules\OnMicrosoft;
use Waterfront\Domain\Microsoft365\Enums\CustomerInfoType;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365ProcessStatus;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;

/** @property Microsoft365CustomerInfo $resource */
class NovaMicrosoft365CustomerResource extends Resource
{
    public static string $model = Microsoft365CustomerInfo::class;

    public static $globallySearchable = false;

    /** @var array<mixed> */
    public static $with = ['customer', 'microsoft365Deployments', 'microsoft365HttpLogs'];

    /** @var array<mixed> */
    public static $search = [
        'tenant_id',
        'tenant_name',
        'kpn_customer_id',
    ];

    public static function getTranslationKey(): string
    {
        return 'microsoft365-customer';
    }

    public function title(): string
    {
        return $this->resource->tenant_name ?? (string) $this->resource->id;
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.microsoft365-customer');
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make(
                self::translate('microsoft365-customer.tenant-name'),
                'tenant_name',
            )
                ->sortable()
                ->rules('required', resolve(OnMicrosoft::class)),
            Text::make(
                self::translate('microsoft365-customer.tenant-id'),
                'tenant_id',
            )->onlyOnDetail(),
            Text::make(
                self::translate('microsoft365-customer.tenant-order-id'),
                'tenant_order_id',
            )->onlyOnDetail(),
            Text::make(
                self::translate('microsoft365-customer.primary-domain'),
                'primary_domain',
            )->onlyOnDetail(),
            Text::make(
                self::translate('microsoft365-customer.primary-domain-status'),
                'primary_domain_status',
            )->onlyOnDetail(),
            Text::make(
                self::translate('microsoft365-customer.kpn-customer-id'),
                'kpn_customer_id',
            )
                ->sortable()
                ->rules('required', resolve(KpnCustomerId::class)),
            BelongsTo::make(
                self::translate('customer.singular'),
                'customer',
                NovaCustomerResource::class,
            )->searchable(),
            Select::make(self::translate('microsoft365-customer.status'), 'technical_status')
                ->options([
                    [
                        'label' => self::translate('microsoft365-customer-info.technical-status.initiated'),
                        'value' => Microsoft365ProcessStatus::INITIATED,
                    ],
                    [
                        'label' => self::translate('microsoft365-customer-info.technical-status.customer-created'),
                        'value' => Microsoft365ProcessStatus::CUSTOMER_CREATED,
                    ],
                    [
                        'label' => self::translate('microsoft365-customer-info.technical-status.active'),
                        'value' => Microsoft365ProcessStatus::ACTIVE,
                    ],
                    [
                        'label' => self::translate('microsoft365-customer-info.technical-status.failed'),
                        'value' => Microsoft365ProcessStatus::FAILED,
                    ],
                ])
                ->displayUsingLabels()
                ->required()
                ->sortable(),
            Select::make('Type', 'type')
                ->options([
                    [
                        'label' => self::translate('microsoft365-customer-info.status.register'),
                        'value' => CustomerInfoType::REGISTER,
                    ],
                    [
                        'label' => self::translate('microsoft365-customer-info.status.transfer'),
                        'value' => CustomerInfoType::TRANSFER,
                    ],
                ])
                ->rules('required')
                ->hideWhenUpdating()
                ->help(self::translate('microsoft365-customerinfo.type_help')),
            DateTime::make('Last sync date', 'synced_at')->sortable()->rules('required'),
            DateTime::make(
                self::translate('microsoft365-customer.mca_signed_at'),
                'mca_signed_at',
            )->showOnIndex(false),
            HasMany::make(
                self::translate('nova-resource-labels.microsoft365-subscriptions'),
                'microsoft365Deployments',
                NovaMicrosoft365DeploymentResource::class,
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

    public function authorizedToDelete(Request $request): bool
    {
        return false;
    }

    public function authorizedToForceDelete(Request $request): bool
    {
        return false;
    }

    /**
     * @return array<int, HtmlCard>
     */
    public function cards(NovaRequest $request): array
    {
        return [
            new HtmlCard()
                ->width('1/2')
                ->center()
                ->html(
                    '<h1 class="font-light mb-4">Irma</h1><a href="https://irma.routit.nl" target="_blank" rel="noopener noreferrer" class="btn btn-default btn-primary" role="button">Login</a>',
                ),

            new HtmlCard()
                ->width('1/2')
                ->center()
                ->html(
                    '<h1 class="font-light mb-4">Microsoft Portal</h1><a href="https://login.microsoftonline.com/" target="_blank" rel="noopener noreferrer" class="btn btn-default btn-primary" role="button">Login</a>',
                ),
        ];
    }

    /**
     * @return array<int, Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            resolve(NovaMicrosoft365RetryCreateKpnCustomerAction::class),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    public function filters(Request $request): array
    {
        return [
            resolve(NovaMicrosoft365CustomerInfoTechnicalStatusFilter::class),
        ];
    }
}
