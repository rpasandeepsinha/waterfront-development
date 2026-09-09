<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Exceptions;

use Exception;
use Throwable;
use Waterfront\Domain\Products\Models\Product;

class NonVpsOsProductException extends Exception
{
    public function __construct(Product $product, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(
            sprintf(
                'Expected VPS OS product. Given product "%s" belongs to product group: "%s".',
                $product->slug,
                $product->productGroup->slug->value
            ),
            $code,
            $previous
        );
    }
}
