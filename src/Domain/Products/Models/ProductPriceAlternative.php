<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int              $product_id
 * @property int              $billing_period
 * @property int              $contract_period
 * @property int              $alternative_product_id
 * @property int              $product_price_component_id
 * @property int              $gross_price
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 *
 * @mixin Builder<ProductPriceAlternative>
 */
class ProductPriceAlternative extends Model
{
    protected $table = 'product_price_alternatives';

    /**
     * @return BelongsTo<Product, $this>
     */
    public function alternativeProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'alternative_product_id');
    }
}
