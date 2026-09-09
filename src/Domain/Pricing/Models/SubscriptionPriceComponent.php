<?php

declare(strict_types=1);

namespace Waterfront\Domain\Pricing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;

/**
 * @property int                $id
 * @property int                $subscription_price_id
 * @property SubscriptionPrice  $subscriptionPrice
 * @property PriceComponentType $type
 * @property ?float             $percentage_discount
 * @property ?non-negative-int  $fixed_discount
 * @property ?non-negative-int  $fixed_price
 * @property non-negative-int   $new_price
 * @property positive-int       $order_applied
 * @property CarbonImmutable    $created_at
 */
class SubscriptionPriceComponent extends Model
{
    protected $table = 'subscription_price_components';

    /**
     * @return BelongsTo<SubscriptionPrice, $this>
     */
    public function subscriptionPrice(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPrice::class);
    }

    protected function casts(): array
    {
        return [
            'type' => PriceComponentType::class,
        ];
    }
}
