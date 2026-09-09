<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Actions;

use Waterfront\Domain\Customers\DTO\DiscountDTO;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\MigrationsPriceDiscounts;

class StoreProductsDiscountAction
{
    public function __construct(
        private readonly MigrationsPriceDiscounts $migrationsPriceDiscounts,
    ) {
    }

    public function execute(DiscountDTO $discount, Customer $customer): void
    {
        $this->migrationsPriceDiscounts->assignDiscount(
            $discount->price,
            $discount->contractPeriod,
            $discount->billingPeriod,
            $discount->baseProductProlongationPrice,
            $customer
        );
    }
}
