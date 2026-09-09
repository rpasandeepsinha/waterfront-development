<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaSubscriptionProductGroupFilter extends Filter
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-filter.subscriptions.product-group');
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->whereIn('product_uuid', Product::query()->where('product_group_id', $value)->pluck('uuid')->toArray());
    }

    /**
     * @return array<mixed>
     */
    public function options(NovaRequest $request): array
    {
        return ProductGroup::query()
            ->orderBy('name')
            ->pluck('id', 'name')
            ->toArray();
    }
}
