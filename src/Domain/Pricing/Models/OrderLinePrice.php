<?php

declare(strict_types=1);

namespace Waterfront\Domain\Pricing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Waterfront\Domain\Orders\Models\OrderLineItem;

/**
 * @property int                                      $id
 * @property int                                      $order_line_item_id
 * @property OrderLineItem                            $orderLine
 * @property non-negative-int                         $net_price
 * @property CarbonImmutable                          $valid_from
 * @property Collection<int, OrderLinePriceComponent> $components
 * @property CarbonImmutable                          $created_at
 */
class OrderLinePrice extends Model
{
    protected $table = 'order_line_prices';

    /**
     * @return BelongsTo<OrderLineItem, $this>
     */
    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(OrderLineItem::class);
    }

    /**
     * @return HasMany<OrderLinePriceComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(OrderLinePriceComponent::class);
    }
}
