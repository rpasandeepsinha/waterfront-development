<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Products\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaProductGroupFilter extends Filter
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('product.relations.product_group');
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where('product_group_id', $value);
    }

    /**
     * @return array<mixed>
     */
    public function options(NovaRequest $request): array
    {
        return ProductGroup::query()->orderBy('name')->pluck('id', 'name')->toArray();
    }
}
