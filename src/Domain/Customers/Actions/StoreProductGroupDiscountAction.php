<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Actions;

use Psr\Log\LoggerInterface;
use Waterfront\Domain\Customers\DTO\ProductGroupDiscountDTO;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Support\Enums\LoggingContextKeys;

class StoreProductGroupDiscountAction
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(ProductGroupDiscountDTO $productGroupDiscount, Customer $customer): void
    {
        $this->logger->info(
            'Attaching product group discount for customer',
            [
                LoggingContextKeys::CUSTOMER_ID => $customer->id,
                LoggingContextKeys::CUSTOMER_NUMBER => $customer->customer_number,
                LoggingContextKeys::META => [
                    'product_group_type' => $productGroupDiscount->productGroupType->value,
                    'discount_percentage' => $productGroupDiscount->discountPercentage,
                ],
            ]
        );

        $productGroup = ProductGroup::query()
            ->where('slug', $productGroupDiscount->productGroupType)
            ->firstOrFail();

        $customer->productGroups()->detach($productGroup->id); // detach in case it's already attached, or else we get an error
        $customer->productGroups()->attach($productGroup->id, ['discount' => $productGroupDiscount->discountPercentage]);
    }
}
