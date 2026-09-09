<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Repositories;

use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerProductDiscount;
use Waterfront\Domain\Products\Models\ProductDiscount;

class DiscountRepository
{
    public function getProductDiscountById(int $id): ?ProductDiscount
    {
        return ProductDiscount::query()->find($id);
    }

    public function hasProductDiscounts(Customer $customer): bool
    {
        return CustomerProductDiscount::query()->where('customer_id', $customer->id)->exists();
    }

    public function hasGroupDiscounts(Customer $customer): bool
    {
        return $customer->productGroups->isNotEmpty();
    }
}
