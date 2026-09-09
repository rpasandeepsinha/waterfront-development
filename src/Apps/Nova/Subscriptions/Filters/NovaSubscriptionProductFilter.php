<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaSubscriptionProductFilter extends Filter
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-filter.subscriptions.product');
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where('product_uuid', $value);
    }

    /**
     * @return array<mixed>
     */
    public function options(NovaRequest $request): array
    {
        return Product::query()
            ->has('subscriptions')
            ->with('productGroup')
            ->orderByRaw('LOWER(name)')
            ->get()
            ->map(fn (Product $product) => [
                'label' => $product->name,
                'value' => $product->uuid,
                'group' => $product->productGroup->name,
            ])
            ->toArray();
    }
}
