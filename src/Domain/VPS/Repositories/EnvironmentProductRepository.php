<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Repositories;

use Waterfront\Domain\VPS\Models\EnvironmentProduct;

class EnvironmentProductRepository
{
    public function getComputeOfferingId(int $productId, int $environmentId): string
    {
        return EnvironmentProduct::query()
            ->where('product_id', $productId)
            ->where('environment_id', $environmentId)
            ->firstOrFail()
            ->product_identifier;
    }
}
