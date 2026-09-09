<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductSpec;

/**
 * @extends Builder<ProductSpec>
 */
class ProductSpecQueryBuilder extends Builder
{
    public function whereProduct(Product $product): self
    {
        return $this->whereHas('product', function (Builder $query) use ($product): void {
            $query->where('id', $product->id);
        });
    }
}
