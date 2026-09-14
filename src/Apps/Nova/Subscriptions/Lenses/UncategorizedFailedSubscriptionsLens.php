<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Lenses;

use Illuminate\Container\Container;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Waterfront\Apps\Nova\Customers\Resources\NovaCustomerResource;
use Waterfront\Apps\Nova\General\Filters\NovaDateEndFilter;
use Waterfront\Apps\Nova\General\Filters\NovaDateStartFilter;
use Waterfront\Apps\Nova\General\Traits\ResolvesActionsAndFilters;
use Waterfront\Apps\Nova\Products\Resources\NovaProductResource;
use Waterfront\Apps\Nova\Subscriptions\Fields\NovaSubscriptionTechnicalStatusSelectField;
use Waterfront\Apps\Nova\Subscriptions\Filters\NovaCategorySubscriptionsFilter;
use Waterfront\Apps\Nova\Subscriptions\Filters\NovaSubscriptionAdministrativeTypeFilter;
use Waterfront\Apps\Nova\Subscriptions\Filters\NovaSubscriptionProductFilter;
use Waterfront\Apps\Nova\Subscriptions\Filters\NovaSubscriptionProductGroupFilter;
use Waterfront\Apps\Nova\Subscriptions\Filters\NovaSubscriptionTechnicalStatusFilter;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;

class UncategorizedFailedSubscriptionsLens extends Lens
{
    use ResolvesActionsAndFilters;

    private readonly TranslatorInterface $translator;

    public function __construct($resource = null)
    {
        parent::__construct($resource);
        $this->translator = Container::getInstance()->make(TranslatorInterface::class);
    }

    /**
     * @param Builder<Subscription> $query
     */
    public static function query(LensRequest $request, Builder $query): Builder|Paginator // @phpstan-ignore-line
    {
        if ($request->viaRelationship()) {
            return $query;
        }

        return $request->withFilters(
            $query
                ->whereDoesntHave('category')
                ->whereIn('technical_status', [
                    TechnicalStatus::ERROR->value,
                    TechnicalStatus::REGISTRATION->value,
                    DomainStatus::FAILED->value,
                    TechnicalStatus::FAILED->value,
                    TechnicalStatus::DELETING_FAILED->value,
                    TechnicalStatus::SUSPENSION_FAILED->value,
                    TechnicalStatus::UNSUSPENSION_FAILED->value,
                ])
                ->whereNotIn(
                    'administrative_status',
                    [
                        AdministrativeStatus::CANCELED->value,
                        AdministrativeStatus::ARCHIVING->value,
                        ...AdministrativeStatus::administrativelyEnded(),
                    ],
                ),
        );
    }

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable()->hideFromIndex(),
            BelongsTo::make(
                $this->translator->translate('subscription.relations.customer'),
                'customer',
                NovaCustomerResource::class,
            )
                ->sortable()
                ->searchable(),
            Text::make('domain'),
            BelongsTo::make(
                $this->translator->translate('subscription.relations.product'),
                'product',
                NovaProductResource::class,
            ),
            Text::make('administrative_status'),
            NovaSubscriptionTechnicalStatusSelectField::make()
                ->displayUsingLabels()
                ->help($this->translator->translate('subscription.info.technical_status')),
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
        ];
    }
}
