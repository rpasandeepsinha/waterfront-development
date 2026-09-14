<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Domains\Resources;

use Illuminate\Http\Request;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Query\Search\SearchableRelation;
use Waterfront\Apps\Nova\Domains\Actions\NovaChangeDomainProviderBusinessUnitAction;
use Waterfront\Apps\Nova\Domains\Actions\NovaFetchDomainAndContactFromRtr;
use Waterfront\Apps\Nova\Domains\Filters\DomainSubscriptionBusinessUnitFilter;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\General\Traits\ResolvesActionsAndFilters;
use Waterfront\Apps\Nova\Subscriptions\Resources\NovaSubscriptionResource;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Domains\Models\DomainProviderBusinessUnit;
use Waterfront\Infra\Common\DateTimeFormat;

/** @property DomainDeployment $resource */
class NovaDomainSubscriptionResource extends Resource
{
    use ResolvesActionsAndFilters;

    public static string $model = DomainDeployment::class;

    public static $globallySearchable = false;

    /** @var array<mixed> */
    public static $with = ['businessUnit'];

    public static function getTranslationKey(): string
    {
        return 'domain-subscription';
    }

    public function title(): string
    {
        return (string) $this->resource->subscription->id;
    }

    /**
     * Get the searchable columns for the resource.
     *
     * @return array<mixed>
     */
    public static function searchableColumns(): array
    {
        return [
            'id',
            new SearchableRelation('subscription', 'domain'),
            new SearchableRelation('businessUnit', 'name'),
            new SearchableRelation('businessUnit', 'slug'),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [
            $this->resolveFilter(DomainSubscriptionBusinessUnitFilter::class),
        ];
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->onlyOnDetail(),
            BelongsTo::make(
                self::translate('subscription.domain-subscription.internal_subscription'),
                'subscription',
                NovaSubscriptionResource::class,
            )
                ->sortable()
                ->exceptOnForms(),
            Text::make(
                self::translate('domain-providers.singular'),
                'provider.slug',
            )->readonly(),
            Select::make(
                name: self::translate('domain-business-unit.singular'),
                attribute: 'domain_business_unit_id',
            )
                ->options(
                    fn (): array => (
                        DomainProviderBusinessUnit::get()->pluck('name', 'id')->toArray()
                        + [null => self::translate('nova-action.select.reset_bu')]
                    ),
                )
                ->onlyOnForms(),
            Text::make(
                self::translate('domain-business-unit.singular'),
                'businessUnit.name',
            )->exceptOnForms(),
            Text::make(
                self::translate('subscription.attributes.domain_status'),
                'domain_status',
            )->exceptOnForms(),
            Code::make(self::translate('subscription.domain-subscription.last_result'), 'last_result')
                ->exceptOnForms()
                ->onlyOnDetail()
                ->json(),
            DateTime::make(
                self::translate('subscription.domain-subscription.last_result_received'),
                'last_result_received',
            )
                ->displayUsing(fn () => $this->resource->last_result_received?->format(DateTimeFormat::DUTCH))
                ->exceptOnForms()
                ->sortable(),
            HasMany::make(
                self::translate('subscription.domain-subscription.contact'),
                'contactOwner',
                NovaDomainContactResource::class,
            )->onlyOnDetail(),
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

    /** @return array<int, Action> */
    public function actions(NovaRequest $request): array
    {
        return [
            $this->resolveAction(NovaChangeDomainProviderBusinessUnitAction::class),
            $this->resolveAction(NovaFetchDomainAndContactFromRtr::class),
        ];
    }
}
