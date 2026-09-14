<?php

declare(strict_types=1);

namespace Waterfront\Domain\Pricing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\Models\Product;

/**
 * @property int                $id
 * @property int                $product_id
 * @property PriceComponentType $type
 * @property int                $contract_period
 * @property int                $billing_period
 * @property non-negative-int   $price
 * @property bool               $orderable
 * @property Product            $product
 * @property CarbonImmutable    $starts_at
 * @property ?CarbonImmutable   $expires_at
 * @property ?CarbonImmutable   $created_at
 * @property ?CarbonImmutable   $updated_at
 *
 * @mixin Builder<ProductPriceComponent>
 */
class ProductPriceComponent extends Model
{
    protected $table = 'product_price_components';

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function casts(): array
    {
        return [
            'type' => PriceComponentType::class,
        ];
    }
}
