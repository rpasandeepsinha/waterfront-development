<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\DNS\Resources;

use Illuminate\Support\Str;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Filters\NovaDateEndFilter;
use Waterfront\Apps\Nova\General\Filters\NovaDateStartFilter;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\General\Traits\ResolvesActionsAndFilters;
use Waterfront\Apps\Nova\General\Traits\ViewOnlyResourceTrait;
use Waterfront\Apps\Nova\Subscriptions\Resources\NovaSubscriptionResource;
use Waterfront\Domain\DNS\Models\DnsRecordChange;
use Waterfront\Infra\Common\DateTimeFormat;

/** @property DnsRecordChange $resource */
class NovaDnsLogResource extends Resource
{
    use ViewOnlyResourceTrait;
    use ResolvesActionsAndFilters;

    public static string $model = DnsRecordChange::class;

    public static $globallySearchable = false;

    public static $displayInNavigation = false;

    public static $perPageViaRelationship = 10;

    /** @var array<mixed> */
    public static $search = [
        'id',
        'changed_by_uuid',
        'ip_address',
        'record_type',
    ];

    public static function getTranslationKey(): string
    {
        return 'dns-logs';
    }

    public function title(): string
    {
        return $this->resource->name;
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make(
                self::translate('ID'),
                'id'
            )->onlyOnDetail(),
            BelongsTo::make(
                self::translate('subscription.domain-subscription.internal_subscription'),
                'subscription',
                NovaSubscriptionResource::class
            )->sortable(),
            Text::make(
                self::translate('dns-logs.record-type'),
                'record_type',
            )->sortable(),
            Text::make(
                self::translate('dns-logs.change-type'),
                'change_type',
            )->sortable(),
            Text::make(
                self::translate('dns-logs.agent-type'),
                'agent_type',
            )->sortable(),
            Text::make(
                self::translate('dns-logs.name'),
                'name',
            )->sortable(),
            Text::make(
                self::translate('dns-logs.content'),
                'content',
            )->displayUsing(fn (): string => Str::limit($this->resource->content, 50)),
            Number::make(
                self::translate('dns-logs.ttl'),
                'ttl',
            )->onlyOnDetail(),
            Number::make(
                self::translate('dns-logs.priority'),
                'priority',
            )->onlyOnDetail(),
            Number::make(
                self::translate('dns-logs.weight'),
                'weight',
            )->onlyOnDetail(),
            Number::make(
                self::translate('dns-logs.port'),
                'port',
            )->onlyOnDetail(),
            Text::make(
                self::translate('dns-logs.changed-by-uuid'),
                'changed_by_uuid',
            )->onlyOnDetail(),
            Code::make(
                self::translate('dns-logs.changed-by-metadata'),
                'changed_by_metadata',
            )->onlyOnDetail()->json()->height(85),
            Text::make(
                self::translate('dns-logs.ip-address'),
                'ip_address',
            )->sortable(),
            DateTime::make(
                self::translate('nova-resource-labels.created-at'),
                'created_at'
            )->displayUsing(fn () => $this->resource->created_at?->format(DateTimeFormat::DUTCH))
            ->sortable(),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [
            $this->resolveFilter(NovaDateStartFilter::class),
            $this->resolveFilter(NovaDateEndFilter::class),
        ];
    }
}
