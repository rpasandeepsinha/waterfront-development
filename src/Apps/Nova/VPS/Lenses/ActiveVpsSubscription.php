<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\VPS\Lenses;

use Illuminate\Container\Container;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean as NovaBoolField;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Waterfront\Apps\Nova\Customers\Resources\NovaCustomerResource;
use Waterfront\Apps\Nova\Subscriptions\Fields\NovaSubscriptionTechnicalStatusSelectField;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Translation\TranslatorInterface;

/**
 * @property-read Subscription $resource
 */
class ActiveVpsSubscription extends Lens
{
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
                ->where('administrative_status', AdministrativeStatus::ACTIVE->value)
                ->where(
                    fn (Builder $where) => $where->where(
                        function (Builder $priceQuery) {
                            $priceQuery->where(
                                'net_price',
                                '!=',
                                0,
                            )->where('gross_price', '!=', 0);
                        },
                    )->whereHas(
                        'product.productGroup',
                        fn (Builder $productGroupQuery): Builder => $productGroupQuery->where(
                            'slug',
                            ProductGroupType::VPS,
                        ),
                    ),
                )
                ->orderBy('created_at', 'desc'),
        );
    }

    public function fields(NovaRequest $request): array
    {
        return [
            BelongsTo::make(
                $this->translator->translate('subscription.relations.customer'),
                'customer',
                NovaCustomerResource::class,
            )
                ->sortable()
                ->searchable(),

            Text::make(
                $this->translator->translate('subscription.relations.product'),
                'product',
            )->resolveUsing(fn (): string => $this->resource->product->name),

            Text::make($this->translator->translate('subscription.type.cloudstack-os'), 'vpsDeployment')->resolveUsing(
                fn () => $this->resource
                    ->children
                    ->firstWhere('product.productGroup.slug', ProductGroupType::CLOUDSTACK_OS)
                    // @phpstan-ignore-next-line property.notFound
                    ->product
                    ->name,
            ),

            NovaSubscriptionTechnicalStatusSelectField::make()
                ->displayUsingLabels()
                ->help($this->translator->translate('subscription.info.technical_status')),

            Date::make($this->translator->translate('subscription.attributes.start_date'), 'start_date')
                ->required()
                ->displayUsing(fn () => $this->resource->start_date->format(DateTimeFormat::DUTCHNOTIME)),

            Date::make($this->translator->translate('subscription.attributes.end_date'), 'end_date')->displayUsing(
                fn () => $this->resource->end_date->format(DateTimeFormat::DUTCHNOTIME),
            ),

            NovaBoolField::make(
                $this->translator->translate('nova-resource-labels.subscription.relation.note'),
                'notes',
            ),
        ];
    }

    public function uriKey(): string
    {
        return 'active-vps-subscription';
    }
}
