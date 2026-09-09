<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Exceptions;

use Exception;
use Throwable;
use Waterfront\Domain\Products\Models\Product;

class NoCloudstackTemplateFoundFromProduct extends Exception
{
    public function __construct(string $templateSlug, Product $product, int $code = 0, ?Throwable $previous = null)
    {
        $message = sprintf(
            'No Cloudstack template found for product "%s" with generated template_slug tag "%s"',
            $product->slug,
            $templateSlug
        );

        parent::__construct($message, $code, $previous);
    }
}
