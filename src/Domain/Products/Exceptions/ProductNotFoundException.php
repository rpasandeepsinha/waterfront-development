<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Waterfront\Domain\Products\Models\Product;

/**
 * @extends ModelNotFoundException<Product>
 */
class ProductNotFoundException extends ModelNotFoundException
{
}
