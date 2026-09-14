<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;

class ProductFilter
{
    private const array BOOLEAN_FILTERS = [
        'orderable',
    ];

    public function __construct(
        private readonly Sorting $sorting,
    ) {
    }

    /**
     * @param Builder<Product> $query
     *
     * @return Builder<Product>
     */
    public function apply(Builder $query, Request $request): Builder
    {
        $search = strtolower($request->string('search')->trim()->toString());
        $hasSearch = $search !== '' && strlen($search) <= 50;
        $hasProductGroupFilter = $request->has('product_group');

        if ($hasSearch || $hasProductGroupFilter) {
            $query
                ->leftJoin('product_groups', 'product_groups.id', '=', 'products.product_group_id')
                ->select('products.*');
        }

        $this->applySearch($query, $request, $hasSearch);
        $this->applyBooleanFilters($query, $request);
        $this->applyProductGroupFilter($query, $request, $hasProductGroupFilter);

        $this->sorting->apply($query, array_filter((array) $request->input('orderBy', []), 'is_string'));

        return $query;
    }

    /** @param Builder<Product> $query */
    private function applySearch(Builder $query, Request $request, bool $hasSearch): void
    {
        if (! $hasSearch) {
            return;
        }

        $search = strtolower($request->string('search')->trim()->toString());

        $query->where(function (Builder $q) use ($search): void {
            $q
                ->where('products.name', 'ilike', "%{$search}%")
                ->orWhere('products.slug', 'ilike', "%{$search}%")
                ->orWhere('products.description', 'ilike', "%{$search}%")
                ->orWhere('product_groups.name', 'ilike', "%{$search}%")
                ->orWhere('product_groups.slug', 'ilike', "%{$search}%");
        });
    }

    /** @param Builder<Product> $query */
    private function applyBooleanFilters(Builder $query, Request $request): void
    {
        foreach (self::BOOLEAN_FILTERS as $field) {
            if (! $request->has($field)) {
                continue;
            }

            $query->where($field, filter_var($request->input($field), FILTER_VALIDATE_BOOLEAN));
        }
    }

    /** @param Builder<Product> $query */
    private function applyProductGroupFilter(Builder $query, Request $request, bool $joinedProductGroups): void
    {
        if (! $request->has('product_group')) {
            return;
        }

        $productGroupType = ProductGroupType::tryFrom($request->string('product_group')->toString());

        if ($productGroupType === null) {
            return;
        }

        if ($joinedProductGroups) {
            $query->where('product_groups.slug', $productGroupType->value);
        } else {
            $query->whereHas('productGroup', function (Builder $productGroupQuery) use ($productGroupType): void {
                $productGroupQuery->where('slug', $productGroupType->value);
            });
        }
    }
}
