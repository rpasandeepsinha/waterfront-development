<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\ProductDiscount;

class ProductDiscountRepository
{
    /**
     * @return Collection<int, ProductDiscount>
     */
    public function getAllUnassignedProductDiscountsWithVolumeDiscount(int $customerId): Collection
    {
        return ProductDiscount::from('product_discounts')
            ->select('product_discounts.*')
            ->join('products AS p', 'product_discounts.product_id', '=', 'p.id')
            ->join('product_groups AS pg', 'p.product_group_id', '=', 'pg.id')
            ->leftJoin('customer_product_discount AS cpd', function ($leftJoin) use ($customerId) {
                $leftJoin->on('product_discounts.id', '=', 'cpd.product_discount_id');
                $leftJoin->where('cpd.customer_id', '=', $customerId);
            })
            ->where('pg.slug', '=', ProductGroupType::VOLUME_DISCOUNT->value)
            ->whereNull('cpd.id')
            ->get();
    }
}
