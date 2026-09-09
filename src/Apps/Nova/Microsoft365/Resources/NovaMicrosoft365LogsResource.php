<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Microsoft365\Resources;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Filters\NovaSpecificDateFilter;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\Subscriptions\Resources\NovaSubscriptionResource;
use Waterfront\Domain\Microsoft365\Models\Microsoft365HttpLog;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/** @property Microsoft365HttpLog $resource */
class NovaMicrosoft365LogsResource extends Resource
{
    public static string $model = Microsoft365HttpLog::class;

    public static $globallySearchable = false;

    /** @var array<mixed> */
    public static $with = ['microsoft365CustomerInfo', 'microsoft365Deployment'];

    /** @var array<mixed> */
    public static $search = [
        'kpn_order_id',
        'kpn_customer_id',
        'tenant_name',
        'xml_root_name',
    ];

    public static function getTranslationKey(): string
    {
        return 'microsoft365-logs';
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.microsoft365-logs');
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            BelongsTo::make(
                self::translate('microsoft365-logs.kpn-customer'),
                'microsoft365CustomerInfo',
                NovaMicrosoft365CustomerResource::class
            ),
            BelongsTo::make(
                self::translate('microsoft365-logs.subscription'),
                'microsoft365Deployment',
                NovaMicrosoft365DeploymentResource::class
            ),
            Text::make(
                self::translate('microsoft365-logs.partner-reference'),
                'partner_reference'
            )->sortable(),
            BelongsTo::make(
                self::translate('subscription.singular'),
                'subscription',
                NovaSubscriptionResource::class
            )->display(function ($subscription) {
                /** @var Subscription $subscription */
                $id = (string) $subscription->id;
                return $id;
            }),
            Text::make(
                self::translate('microsoft365-logs.xml-name'),
                'xml_root_name'
            )->onlyOnIndex(),
            Text::make(
                self::translate('microsoft365-customer.kpn-customer-id'),
                'kpn_customer_id'
            )->onlyOnDetail(),
            Text::make(
                'KPN order ID',
                'kpn_order_id'
            )->onlyOnDetail(),
            Code::make(
                self::translate('microsoft365-logs.log'),
                'log'
            )->onlyOnDetail()
            ->language('xml'),
            DateTime::make(
                self::translate('microsoft365-logs.time'),
                'created_at'
            )->sortable(),
        ];
    }

    /**
     * @return array<int, NovaSpecificDateFilter>
     */
    public function filters(Request $request): array
    {
        return [
            new NovaSpecificDateFilter(),
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
}
