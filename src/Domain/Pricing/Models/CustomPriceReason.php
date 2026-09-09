<?php

declare(strict_types=1);

namespace Waterfront\Domain\Pricing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Waterfront\Domain\Products\Enums\CustomPriceReasonType;

/**
 * @property int                   $id
 * @property int                   $subscription_price_id
 * @property SubscriptionPrice     $subscriptionPrice
 * @property CustomPriceReasonType $reason
 * @property ?CarbonImmutable      $created_at
 * @property ?CarbonImmutable      $updated_at
 *
 * @mixin Builder<CustomPriceReason>
 */
class CustomPriceReason extends Model
{
    protected $table = 'custom_price_reasons';

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
            'reason' => CustomPriceReasonType::class,
        ];
    }
}
