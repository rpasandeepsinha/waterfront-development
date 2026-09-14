<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Waterfront\Domain\Products\Models\ProductPromotion;

class ProductPromotionsRepository
{
    /** @return Collection<int, ProductPromotion> */
    public function findAllActiveProductPromotions(): Collection
    {
        $date = CarbonImmutable::now();

        /** @var Collection<int,ProductPromotion> $productPromotions */
        $productPromotions = ProductPromotion::query()
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->get();

        return $productPromotions;
    }
}
