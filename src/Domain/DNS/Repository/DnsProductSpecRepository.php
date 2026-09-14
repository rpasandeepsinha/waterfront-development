<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Repository;

use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Exceptions\InvalidProductGroupException;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;

class DnsProductSpecRepository
{
    public function __construct(
        private readonly ProductSpecRepository $productSpecRepository,
    ) {
    }

    public function isPremiumDns(Product $product): bool
    {
        $this->assertDnsProduct($product);

        return $this->productSpecRepository->booleanSpecificationIsTrue(
            $product,
            ProductSpecName::DNS_IS_PREMIUM,
        );
    }

    public function allowDnsRecordEditing(Product $product): bool
    {
        $this->assertDnsProduct($product);

        return $this->productSpecRepository->booleanSpecificationIsTrue(
            $product,
            ProductSpecName::DNS_CAN_EDIT_RECORDS,
        );
    }

    private function assertDnsProduct(Product $product): void
    {
        if ($product->productGroup->slug !== ProductGroupType::DNS) {
            throw new InvalidProductGroupException();
        }
    }
}
