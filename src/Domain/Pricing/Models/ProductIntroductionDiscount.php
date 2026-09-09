<?php

declare(strict_types=1);

namespace Waterfront\Domain\Pricing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Waterfront\Domain\Products\Models\Product;

/**
 * @property int               $product_id
 * @property ?non-negative-int $max_uses_per_customer
 * @property ?non-negative-int $first_months_discount_period
 * @property int               $contract_period
 * @property Product           $product
 * @property ?CarbonImmutable  $created_at
 * @property ?CarbonImmutable  $updated_at
 * @property ?CarbonImmutable  $deleted_at
 *
 * @mixin Builder<ProductIntroductionDiscount>
 */
class ProductIntroductionDiscount extends Model
{
    use SoftDeletes;

    protected $table = 'product_introduction_discounts';

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
