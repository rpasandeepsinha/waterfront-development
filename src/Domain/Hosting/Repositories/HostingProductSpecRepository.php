<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Repositories;

use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;

class HostingProductSpecRepository
{
    public function __construct(
        private readonly ProductSpecRepository $productSpecRepository
    ) {
    }

    public function allowHostingCoupling(Product $product): bool
    {
        return $this->productSpecRepository->booleanSpecificationIsTrue(
            $product,
            ProductSpecName::DNS_CAN_COUPLE_HOSTING_OR_REDIRECT
        );
    }
}
