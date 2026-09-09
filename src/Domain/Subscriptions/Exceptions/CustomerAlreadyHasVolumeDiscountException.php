<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Exceptions;

use Exception;
use Throwable;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductDiscount;

class CustomerAlreadyHasVolumeDiscountException extends Exception
{
    public function __construct(Customer $customer, Product $product, int $code = 0, ?Throwable $previous = null)
    {
        /** @var ProductDiscount|null $customerProductDiscount */
        $customerProductDiscount = $customer->productDiscount()->first();

        parent::__construct(
            sprintf(
                'Wanted to attach discount with product slug {%s}, but customer %d already has a volume discount {%s}',
                $product->slug,
                $customer->id,
                $customerProductDiscount?->product->slug
            ),
            $code,
            $previous
        );
    }
}
