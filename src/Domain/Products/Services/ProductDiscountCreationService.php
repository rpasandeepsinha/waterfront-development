<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Services;

use Waterfront\Domain\Products\Models\ProductDiscount;

class ProductDiscountCreationService
{
    public function createDiscount(string $name, ?string $description): ProductDiscount
    {
        $productDiscount = new ProductDiscount();
        $productDiscount->name = $name;
        $productDiscount->description = $description;
        $productDiscount->save();

        return $productDiscount;
    }
}
