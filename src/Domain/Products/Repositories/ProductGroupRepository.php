<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Repositories;

use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\ProductGroup;

class ProductGroupRepository
{
    public function getByType(ProductGroupType $type): ProductGroup
    {
        return ProductGroup::where('slug', $type->value)->firstOrFail();
    }
}
