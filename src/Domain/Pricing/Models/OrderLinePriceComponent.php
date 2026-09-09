<?php

declare(strict_types=1);

namespace Waterfront\Domain\Pricing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;

/**
 * @property int                $id
 * @property int                $order_line_price_id
 * @property OrderLinePrice     $orderLinePrice
 * @property PriceComponentType $type
 * @property ?float             $percentage_discount
 * @property ?non-negative-int  $fixed_discount
 * @property ?non-negative-int  $fixed_price
 * @property non-negative-int   $new_price
 * @property positive-int       $order_applied
 * @property CarbonImmutable    $created_at
 */
class OrderLinePriceComponent extends Model
{
    protected $table = 'order_line_price_components';

    /**
     * @return BelongsTo<OrderLinePrice, $this>
     */
    public function orderLinePrice(): BelongsTo
    {
        return $this->belongsTo(OrderLinePrice::class);
    }

    protected function casts(): array
    {
        return [
            'type' => PriceComponentType::class,
        ];
    }
}
