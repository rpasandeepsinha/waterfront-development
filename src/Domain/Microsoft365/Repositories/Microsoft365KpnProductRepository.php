<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Repositories;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Waterfront\Domain\Microsoft365\Models\Microsoft365KpnProduct;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class Microsoft365KpnProductRepository
{
    /**
     * @throws ModelNotFoundException
     */
    public function getBySubscription(Subscription $subscription): Microsoft365KpnProduct
    {
        return $this->getByProductAndPeriod($subscription->product, $subscription->contract_period);
    }

    /**
     * @throws ModelNotFoundException
     */
    private function getByProductAndPeriod(Product $product, int $contractPeriod): Microsoft365KpnProduct
    {
        return Microsoft365KpnProduct::where([
            'product_id' => $product->id,
            'contract_period' => $contractPeriod,
        ])->firstOrFail();
    }
}
